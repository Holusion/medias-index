<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Auth\Guard;
use MediasIndex\Http\NotFound;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Storage\ProjectTotals;
use MediasIndex\View\View;

/**
 * A project: the client's projects still in the sidebar, this one's medias in
 * the main column.
 */
final readonly class ProjectController
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
        $projectSlug = $parameters['project'];
        $this->guard->requireClient($clientSlug);

        $clientId = $this->clients->findIdBySlug($clientSlug);

        if ($clientId === null) {
            throw new NotFound('No such client: ' . $clientSlug);
        }

        $projects = $this->projects->listForClient($clientId);
        $project = self::find($projects, $projectSlug);

        if ($project === null) {
            throw new NotFound('No such project: ' . $projectSlug);
        }

        $page = max(1, $request->queryInt('page', 1));

        return Response::html($this->view->render('layout', [
            'title' => $projectSlug,
            'breadcrumb' => [$clientSlug => null, $projectSlug => null],
            'bodyModifiers' => 'is-split is-selected',
            'sidebar' => $this->view->render('partials/sidebar-projects', [
                'clientSlug' => $clientSlug,
                'projects' => $projects,
                'currentSlug' => $projectSlug,
            ]),
            'content' => $this->view->render('project/show', [
                'clientSlug' => $clientSlug,
                'project' => $project,
                'medias' => $this->medias->search($project->id, $request->query('q'), $page, self::PER_PAGE),
                'page' => $page,
            ]),
        ]));
    }

    /** @param list<ProjectTotals> $projects */
    private static function find(array $projects, string $slug): ?ProjectTotals
    {
        foreach ($projects as $project) {
            if ($project->slug === $slug) {
                return $project;
            }
        }

        return null;
    }
}
