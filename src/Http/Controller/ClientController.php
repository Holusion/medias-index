<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Auth\Guard;
use MediasIndex\Http\Request;
use MediasIndex\Http\NotFound;
use MediasIndex\Http\Response;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\ClientTotals;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Storage\ProjectTotals;
use MediasIndex\View\View;

/**
 * A client's page: projects in the sidebar, the selected project's medias in the
 * main column.
 *
 * The selection lives in the query string rather than in a session, so a page is
 * shareable and the mobile "one pane at a time" behaviour needs no script.
 */
final readonly class ClientController
{
    private const PER_PAGE = 25;

    public function __construct(
        private ClientRepository $clients,
        private ProjectRepository $projects,
        private MediaRepository $medias,
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

        $projects = $this->projects->listForClient($clientId);
        $selected = $this->selectedProject($projects, $request->query('p'));

        $page = max(1, $request->queryInt('page', 1));
        $medias = $selected !== null
            ? $this->medias->search($selected->id, $request->query('q'), $page, self::PER_PAGE)
            : null;

        return Response::html($this->view->render('layout', [
            'title' => $clientSlug,
            'breadcrumb' => [$clientSlug => null],
            'bodyModifiers' => 'is-split' . ($selected !== null ? ' is-selected' : ''),
            'sidebar' => $this->view->render('partials/sidebar-projects', [
                'clientSlug' => $clientSlug,
                'projects' => $projects,
                'selected' => $selected,
            ]),
            'content' => $this->view->render('client/show', [
                'clientSlug' => $clientSlug,
                'clientTotals' => $this->clientTotals($clientId),
                'selected' => $selected,
                'medias' => $medias,
                'page' => $page,
            ]),
        ]));
    }

    /**
     * A project that does not exist is an error, not an empty selection.
     *
     * Silently falling back to "nothing selected" would answer 200 to a stale or
     * mistyped link and leave the visitor to work out that what they asked for is
     * gone — the same reasoning as a deleted media under /files/ returning 404.
     *
     * @param list<ProjectTotals> $projects
     */
    private function selectedProject(array $projects, ?string $slug): ?ProjectTotals
    {
        if ($slug === null) {
            return null;
        }

        foreach ($projects as $project) {
            if ($project->slug === $slug) {
                return $project;
            }
        }

        throw new NotFound('No such project: ' . $slug);
    }

    private function clientTotals(int $clientId): ?ClientTotals
    {
        foreach ($this->clients->listTotals() as $client) {
            if ($client->id === $clientId) {
                return $client;
            }
        }

        return null;
    }
}
