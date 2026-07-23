<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

use MediasIndex\Indexer\Probe\GenericProbe;
use MediasIndex\Indexer\Probe\KrpanoProbe;
use MediasIndex\Indexer\Probe\MediaProbe;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Storage\ScanRepository;
use Throwable;

/**
 * Brings the database in step with the disk.
 *
 * One entry point for both triggers — the OVH cron task and the POST upload
 * hook — because the only thing that differs between them is the scope and who
 * to blame in the audit trail.
 */
final class Scanner
{
    /** @var list<MediaProbe> */
    private array $probes;

    /**
     * @param list<MediaProbe>|null $probes tried in order, most specific first.
     *                                       Null uses the built-in chain;
     *                                       GenericProbe is always appended as
     *                                       the fallback so every media is
     *                                       claimed by something.
     */
    public function __construct(
        private readonly Tree $tree,
        private readonly MediaInspector $inspector,
        private readonly ClientRepository $clients,
        private readonly ProjectRepository $projects,
        private readonly MediaRepository $medias,
        private readonly ScanRepository $scans,
        ?array $probes = null,
        private readonly ?ThumbnailGenerator $thumbnails = null,
    ) {
        $this->probes = [...($probes ?? self::defaultProbes()), new GenericProbe()];
    }

    /** @return list<MediaProbe> most specific first */
    public static function defaultProbes(): array
    {
        return [new KrpanoProbe()];
    }

    public function scan(ScanScope $scope, string $triggeredBy = 'cli'): ScanResult
    {
        $startedAt = time();
        $scanId = $this->scans->open($triggeredBy, $scope->describe(), $startedAt);

        try {
            $result = $this->run($scope, $scanId, $startedAt);
            $this->scans->close($scanId, ScanRepository::OK, $result->toArray());

            return $result;
        } catch (Throwable $e) {
            $this->scans->close($scanId, ScanRepository::FAILED, [], $e->getMessage());

            throw $e;
        }
    }

    private function run(ScanScope $scope, int $scanId, int $startedAt): ScanResult
    {
        $clientCount = 0;
        $projectCount = 0;
        $mediaCount = 0;
        $unusable = 0;
        $touchedClientIds = [];
        $touchedProjectIds = [];

        $clientSlugs = $scope->client !== null ? [$scope->client] : $this->tree->clients();

        foreach ($clientSlugs as $clientSlug) {
            if (!is_dir($this->tree->path($clientSlug))) {
                continue;
            }

            $clientId = $this->clients->upsert(
                $clientSlug,
                $clientSlug,
                (int) filectime($this->tree->path($clientSlug)),
                $scanId,
                $startedAt,
            );
            $touchedClientIds[] = $clientId;
            $clientCount++;

            $projectSlugs = $scope->project !== null ? [$scope->project] : $this->tree->projects($clientSlug);

            foreach ($projectSlugs as $projectSlug) {
                $projectPath = $this->tree->path($clientSlug, $projectSlug);

                if (!is_dir($projectPath)) {
                    continue;
                }

                $projectId = $this->projects->upsert(
                    $clientId,
                    $projectSlug,
                    $projectSlug,
                    (int) filectime($projectPath),
                    $scanId,
                    $startedAt,
                );
                $touchedProjectIds[] = $projectId;
                $projectCount++;

                foreach ($this->tree->medias($clientSlug, $projectSlug) as $mediaSlug) {
                    $mediaDir = $this->tree->path($clientSlug, $projectSlug, $mediaSlug);
                    $facts = $this->inspector->inspect($mediaDir);
                    $probe = $this->probe($mediaDir, $facts);

                    $mediaId = $this->medias->upsert(
                        $projectId,
                        $mediaSlug,
                        $facts,
                        $probe,
                        $scanId,
                        $startedAt,
                    );
                    $mediaCount++;

                    if ($this->thumbnails !== null) {
                        $this->medias->setThumbnail(
                            $mediaId,
                            $this->thumbnail($mediaId, $mediaDir, $probe->thumbnailSource),
                        );
                    }

                    if (!$probe->isUsable()) {
                        $unusable++;
                    }
                }
            }
        }

        // Anything inside the scope that this scan did not touch is gone from
        // disk. Rows outside the scope are left alone — a scan of one client
        // must not bury the others.
        $deletedAt = time();
        $deletedMedias = $this->medias->sweep(
            $scanId,
            $deletedAt,
            $scope->isEverything() ? null : $touchedProjectIds,
        );
        $deletedProjects = $this->projects->sweep(
            $scanId,
            $deletedAt,
            $scope->isEverything() ? null : $touchedClientIds,
        );
        $deletedClients = $scope->isEverything()
            ? $this->clients->sweep($scanId, $deletedAt)
            : 0;

        return new ScanResult(
            scanId: $scanId,
            clients: $clientCount,
            projects: $projectCount,
            medias: $mediaCount,
            unusable: $unusable,
            deletedClients: $deletedClients,
            deletedProjects: $deletedProjects,
            deletedMedias: $deletedMedias,
            durationSeconds: time() - $startedAt,
        );
    }

    /**
     * A media that no longer nominates a source loses its thumbnail rather than
     * keeping the one from a previous scan.
     */
    private function thumbnail(int $mediaId, string $mediaDir, ?string $source): ?string
    {
        if ($this->thumbnails === null) {
            return null;
        }

        if ($source === null) {
            $this->thumbnails->discardPreviousVersions($mediaId);

            return null;
        }

        return $this->thumbnails->generate($mediaId, $mediaDir . '/' . $source);
    }

    private function probe(string $mediaDir, MediaFacts $facts): Probe\ProbeResult
    {
        foreach ($this->probes as $probe) {
            $result = $probe->probe($mediaDir, $facts);

            if ($result !== null) {
                return $result;
            }
        }

        // Unreachable: GenericProbe claims everything.
        return new Probe\ProbeResult(type: MediaType::UNKNOWN);
    }
}
