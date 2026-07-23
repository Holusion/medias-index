<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Auth\Guard;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\View\View;

/**
 * Living reference for the components the interface is built from.
 */
final readonly class StyleguideController
{
    public function __construct(
        private ClientRepository $clients,
        private View $view,
        private Guard $guard,
    ) {
    }

    public function show(Request $request): Response
    {
        $this->guard->requireAdmin();

        return Response::html($this->view->render('layout', [
            'title' => 'Références de style',
            'breadcrumb' => ['Références de style' => null],
            'sidebar' => $this->view->render('partials/sidebar-clients', [
                'clients' => $this->clients->listTotals(),
            ]),
            'content' => $this->view->render('styleguide'),
        ]));
    }
}
