<?php

namespace App\Domains\Matches\Exceptions;

use RuntimeException;

class MatchIngestionConflict extends RuntimeException
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public readonly string $category, public readonly array $metadata = [])
    {
        parent::__construct('The match operation conflicts with canonical server state.');
    }
}
