<?php

declare(strict_types=1);

namespace MediasIndex\Search;

/**
 * Substring matching with LIKE.
 *
 * Correct and fast enough at the scale this app targets — a few hundred medias
 * per project — and it behaves identically on MySQL and PostgreSQL, which the
 * alternatives do not.
 *
 * LOWER() on both sides is what makes that true: MySQL's default collation
 * compares case-insensitively while PostgreSQL does not, so without it the same
 * query would return different results on the two engines. It does cost any
 * chance of using an index on the column, which is the main reason this is a
 * stop-gap rather than the answer.
 */
final class LikeSearch implements SearchStrategy
{
    public function condition(string $column, string $query): array
    {
        $terms = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($terms === []) {
            return ['', []];
        }

        $conditions = [];
        $params = [];

        // Every term must appear, in any order — closer to what someone typing
        // two words expects than matching the phrase verbatim.
        foreach ($terms as $term) {
            $conditions[] = sprintf('LOWER(%s) LIKE LOWER(?)', $column);
            $params[] = '%' . self::escapeWildcards($term) . '%';
        }

        return ['(' . implode(' AND ', $conditions) . ')', $params];
    }

    /** Otherwise a query containing % or _ would match far more than it should. */
    private static function escapeWildcards(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
