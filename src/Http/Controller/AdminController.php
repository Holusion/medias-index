<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Auth\Guard;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\ClientTotals;
use MediasIndex\View\View;

/**
 * The overview: every client, with the headline figures.
 */
final readonly class AdminController
{
    public function __construct(
        private ClientRepository $clients,
        private View $view,
        private Guard $guard,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->guard->requireAdmin();

        $clients = $this->clients->listTotals();

        return Response::html($this->view->render('layout', [
            'title' => "Vue d'ensemble",
            'breadcrumb' => [],
            'sidebar' => $this->view->render('partials/sidebar-clients', ['clients' => $clients]),
            'content' => $this->view->render('admin/index', [
                'clients' => $clients,
                'totalSizeBytes' => $this->clients->totalSizeBytes(),
                'projectCount' => array_sum(
                    array_map(static fn (ClientTotals $c): int => $c->projectCount, $clients),
                ),
                'mediaCount' => array_sum(
                    array_map(static fn (ClientTotals $c): int => $c->mediaCount, $clients),
                ),
            ]),
        ]));
    }
}
