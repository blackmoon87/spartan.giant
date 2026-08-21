<?php

declare(strict_types=1);

namespace Spartan\Database;

interface DialectInterface
{
    public function quoteIdentifier(string $identifier): string;
    public function quoteTable(string $table): string;
}
