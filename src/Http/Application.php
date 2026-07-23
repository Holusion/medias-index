<?php

declare(strict_types=1);

namespace MediasIndex\Http;

use MediasIndex\Auth\AccessDenied;
use MediasIndex\Auth\Guard;
use MediasIndex\Auth\NullGuard;
use MediasIndex\Http\Controller\AdminController;
use MediasIndex\Http\Controller\ClientController;
use MediasIndex\Http\Controller\DoctorController;
use MediasIndex\Http\Controller\ErrorController;
use MediasIndex\Http\Controller\StyleguideController;
use MediasIndex\Search\LikeSearch;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Support\Config;
use MediasIndex\Support\Database;
use MediasIndex\View\Urls;
use MediasIndex\View\View;
use Throwable;

/**
 * Wires the object graph and routes a request through it.
 *
 * Deliberately hand-wired rather than driven by a container: there are a dozen
 * objects, the wiring fits on a screen, and it is one less dependency on a host
 * where nothing can be installed.
 *
 * public/index.php holds nothing but the two lines that call this, so the whole
 * request path can be exercised from a test.
 */
final class Application
{
    private function __construct(
        private readonly Router $router,
        private readonly ErrorPage $errorPage,
    ) {
    }

    public static function create(Config $config, ?Guard $guard = null): self
    {
        $pdo = (new Database($config))->pdo();
        $guard ??= new NullGuard();

        $urls = new Urls(
            $config->url('urls.files'),
            $config->url('urls.thumbs'),
            $config->url('urls.origin'),
        );

        // Templates reach the URL builder through this rather than through a
        // global; every render() call inherits it.
        $view = new View(Config::rootDir() . '/templates', ['urls' => $urls]);

        $clients = new ClientRepository($pdo);
        $projects = new ProjectRepository($pdo);
        $medias = new MediaRepository($pdo, new LikeSearch());

        $admin = new AdminController($clients, $view, $guard);
        $client = new ClientController($clients, $projects, $medias, $view, $guard);
        $styleguide = new StyleguideController($clients, $view, $guard);
        $doctor = new DoctorController($guard);
        $errorPage = new ErrorPage($view);
        $errors = new ErrorController($errorPage);

        $router = new Router();
        $router->get('/', $admin->index(...));
        $router->get('/c/{client}', $client->show(...));
        $router->get('/styleguide', $styleguide->show(...));
        $router->get('/doctor', $doctor->show(...));
        // Where Apache's ErrorDocument directives point; see deploy/www.htaccess.
        $router->get('/error/{status}', $errors->show(...));

        return new self($router, $errorPage);
    }

    public function handle(Request $request): Response
    {
        // Apache handed us an error it produced itself — a stale link to a
        // deleted media, a refused directory listing — and REQUEST_URI is still
        // the URL that failed, so there is nothing to route.
        if ($request->errorStatus !== null) {
            return $this->errorPage->render($request->errorStatus);
        }

        try {
            return $this->router->dispatch($request) ?? $this->errorPage->render(404);
        } catch (NotFound) {
            return $this->errorPage->render(404);
        } catch (AccessDenied) {
            return $this->errorPage->render(403);
        } catch (Throwable $e) {
            // Never leak a stack trace or a DSN to the browser. The details go to
            // the server log, which is where they are of any use.
            error_log(sprintf('[medias-index] %s: %s', $e::class, $e->getMessage()));

            return $this->errorPage->render(500);
        }
    }
}
