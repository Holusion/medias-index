<?php

declare(strict_types=1);

namespace MediasIndex\Search;

/**
 * How a free-text query is turned into a SQL condition.
 *
 * The seam for the deferred search work: swapping LikeSearch for a MySQL
 * FULLTEXT / MATCH AGAINST or a PostgreSQL tsvector implementation is a new
 * class plus an index, with no change to the repositories or the UI. medias
 * .search_text is populated from the first scan so no backfill is needed.
 */
interface SearchStrategy
{
    /**
     * A condition to AND into a WHERE clause, with its parameters.
     *
     * The column is passed in rather than hard-coded so a strategy can be
     * applied to whichever haystack the caller has.
     *
     * @return array{0: string, 1: list<mixed>} SQL fragment and its parameters
     */
    public function condition(string $column, string $query): array;
}
