<?php

declare(strict_types=1);

namespace Spartan\Database;

class SqliteDialect implements DialectInterface
{
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '', $identifier) . '"';
    }

    public function quoteTable(string $table): string
    {
        return '"' . str_replace('"', '', $table) . '"';
    }
}
