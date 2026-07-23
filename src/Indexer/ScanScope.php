<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

/**
 * What part of the tree a scan covers.
 *
 * The MVP only ever builds all(), but everything downstream — the walk and,
 * more importantly, the delete sweep — already honours a scope. That is what
 * turns the deferred partial-scan work into parsing the upload hook's POST
 * parameters into one of these, rather than a rewrite: a scan that looked at one
 * client must never bury the clients it did not look at.
 */
final readonly class ScanScope
{
    private function __construct(
        public ?string $client = null,
        public ?string $project = null,
    ) {
    }

    public static function all(): self
    {
        return new self();
    }

    public static function client(string $client): self
    {
        return new self($client);
    }

    public static function project(string $client, string $project): self
    {
        return new self($client, $project);
    }

    public function isEverything(): bool
    {
        return $this->client === null;
    }

    /** Stored on the scan record, and shown in the audit trail. */
    public function describe(): string
    {
        return match (true) {
            $this->client === null => 'all',
            $this->project === null => 'client:' . $this->client,
            default => 'project:' . $this->client . '/' . $this->project,
        };
    }
}
