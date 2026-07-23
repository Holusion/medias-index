<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

/**
 * What a scan did. Written to the scan record and printed by bin/scan.php.
 */
final readonly class ScanResult
{
    public function __construct(
        public int $scanId,
        public int $clients = 0,
        public int $projects = 0,
        public int $medias = 0,
        public int $unusable = 0,
        public int $deletedClients = 0,
        public int $deletedProjects = 0,
        public int $deletedMedias = 0,
        public int $durationSeconds = 0,
    ) {
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'clients' => $this->clients,
            'projects' => $this->projects,
            'medias' => $this->medias,
            'unusable' => $this->unusable,
            'deleted_clients' => $this->deletedClients,
            'deleted_projects' => $this->deletedProjects,
            'deleted_medias' => $this->deletedMedias,
            'duration_seconds' => $this->durationSeconds,
        ];
    }
}
