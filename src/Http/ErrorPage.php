<?php

declare(strict_types=1);

namespace MediasIndex\Http;

use MediasIndex\View\View;

/**
 * The one place an error page is built.
 *
 * Used both by the application, when it handles an error itself, and by
 * ErrorController, which is what Apache's ErrorDocument directives point at —
 * so a 404 on a stale content link under /files/ looks exactly like a 404 on an
 * unknown route, even though only one of them ever reaches PHP routing.
 */
final readonly class ErrorPage
{
    /** Anything not listed falls back to the 500 wording, never to a blank page. */
    private const MESSAGES = [
        400 => ['Requête invalide', "La requête n'a pas pu être interprétée."],
        403 => ['Accès refusé', "Vous n'avez pas les droits nécessaires pour consulter cette page."],
        404 => ['Introuvable', "Cette page ou ce contenu n'existe pas, ou n'est plus indexé."],
        500 => ['Erreur', 'Une erreur est survenue. Le détail se trouve dans le journal du serveur.'],
        503 => ['Indisponible', 'Le service est momentanément indisponible.'],
    ];

    public function __construct(private View $view)
    {
    }

    public function render(int $status): Response
    {
        $status = isset(self::MESSAGES[$status]) ? $status : 500;
        [$title, $message] = self::MESSAGES[$status];

        $response = Response::html(
            $this->view->render('layout', [
                'title' => $title,
                'breadcrumb' => [],
                'sidebar' => '',
                'content' => $this->view->render('errors/error', [
                    'status' => $status,
                    'heading' => $title,
                    'message' => $message,
                ]),
            ]),
            $status,
        );

        // Errors are the most transient thing this app serves: a 404 stops being
        // true the moment the content is re-indexed. Content under /files/ is
        // cached generously, and a cached error page would outlive its cause.
        return new Response(
            $response->body,
            $response->status,
            [...$response->headers, 'Cache-Control' => 'no-store'],
        );
    }
}
