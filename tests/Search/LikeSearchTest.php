<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Search;

use MediasIndex\Search\LikeSearch;
use PHPUnit\Framework\TestCase;

final class LikeSearchTest extends TestCase
{
    public function testEmptyQueryProducesNoCondition(): void
    {
        [$sql, $params] = (new LikeSearch())->condition('search_text', '   ');

        self::assertSame('', $sql);
        self::assertSame([], $params);
    }

    public function testEveryTermMustMatch(): void
    {
        [$sql, $params] = (new LikeSearch())->condition('search_text', 'salle bleue');

        self::assertSame(
            '(LOWER(search_text) LIKE LOWER(?) AND LOWER(search_text) LIKE LOWER(?))',
            $sql,
        );
        self::assertSame(['%salle%', '%bleue%'], $params);
    }

    /**
     * LOWER() on both sides is what makes MySQL and PostgreSQL agree: MySQL's
     * default collation compares case-insensitively, PostgreSQL's does not.
     */
    public function testComparisonIsCaseInsensitiveOnBothEngines(): void
    {
        [$sql] = (new LikeSearch())->condition('search_text', 'Salle');

        self::assertStringContainsString('LOWER(search_text) LIKE LOWER(?)', $sql);
    }

    /** Otherwise searching for "50%" would match nearly everything. */
    public function testWildcardsInTheQueryAreEscaped(): void
    {
        [, $params] = (new LikeSearch())->condition('search_text', '50%_x');

        self::assertSame(['%50\\%\\_x%'], $params);
    }

    public function testBackslashesAreEscapedBeforeWildcards(): void
    {
        [, $params] = (new LikeSearch())->condition('search_text', 'a\\b');

        self::assertSame(['%a\\\\b%', ], $params);
    }
}
