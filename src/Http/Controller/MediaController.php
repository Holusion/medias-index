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
 * A media's own page: what used to be the embed dialog.
 *
 * A page rather than a dialog because the thing people do with it is send it to
 * someone — "here is the code for this" is a link, not a modal you have to
 * describe how to reach.
 */
final readonly class MediaController
{
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
        $mediaSlug = $parameters['media'];
        $this->guard->requireClient($clientSlug);

        $clientId = $this->clients->findIdBySlug($clientSlug);

        if ($clientId === null) {
            throw new NotFound('No such client: ' . $clientSlug);
        }

        $project = self::find($this->projects->listForClient($clientId), $projectSlug);

        if ($project === null) {
            throw new NotFound('No such project: ' . $projectSlug);
        }

        $media = $this->medias->findBySlug($project->id, $mediaSlug);

        if ($media === null) {
            throw new NotFound('No such media: ' . $mediaSlug);
        }

        return Response::html($this->view->render('layout', [
            'title' => $media->name,
            'breadcrumb' => [$clientSlug => null, $projectSlug => null, $mediaSlug => null],
            'bodyModifiers' => 'is-split is-selected',
            // One level deeper than the project page: the sidebar lists what is
            // inside the location the crumbs name, which here is the project.
            'sidebar' => $this->view->render('partials/sidebar-medias', [
                'clientSlug' => $clientSlug,
                'project' => $project,
                'medias' => $this->medias->listForProject($project->id),
                'currentSlug' => $mediaSlug,
            ]),
            'content' => $this->view->render('media/show', [
                'clientSlug' => $clientSlug,
                'project' => $project,
                'media' => $media,
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
