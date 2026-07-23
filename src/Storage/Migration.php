<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

/**
 * One schema change.
 *
 * Migrations return statements rather than executing them, so the SQL a given
 * dialect produces can be asserted in a unit test without a database.
 */
interface Migration
{
    /** @return list<string> statements, executed in order */
    public function statements(Dialect $dialect): array;
}
