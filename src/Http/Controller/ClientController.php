<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Auth\Guard;
use MediasIndex\Http\NotFound;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\ClientTotals;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\View\View;

/**
 * A client: its projects in the sidebar, its own figures in the main column.
 *
 * The main column shows the current level and nothing above it — the ancestors
 * are the sidebar's breadcrumb. Repeating them as stacked headings made every
 * page look like it was about its parent.
 */
final readonly class ClientController
{
    public function __construct(
        private ClientRepository $clients,
        private ProjectRepository $projects,
        private View $view,
        private Guard $guard,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $clientSlug = $parameters['client'];
        $this->guard->requireClient($clientSlug);

        $clientId = $this->clients->findIdBySlug($clientSlug);

        if ($clientId === null) {
            throw new NotFound('No such client: ' . $clientSlug);
        }

        return Response::html($this->view->render('layout', [
            'title' => $clientSlug,
            'breadcrumb' => [$clientSlug => null],
            'sidebar' => $this->view->render('partials/sidebar-projects', [
                'clientSlug' => $clientSlug,
                'projects' => $this->projects->listForClient($clientId),
                'currentSlug' => null,
            ]),
            'content' => $this->view->render('client/show', [
                'clientSlug' => $clientSlug,
                'clientTotals' => $this->totals($clientId),
            ]),
        ]));
    }

    private function totals(int $clientId): ?ClientTotals
    {
        foreach ($this->clients->listTotals() as $client) {
            if ($client->id === $clientId) {
                return $client;
            }
        }

        return null;
    }
}
