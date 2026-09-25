<?php

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

class InvalidEntityAliasException extends RuntimeException
{
    public static function unsupportedType(string $entityType): self
    {
        return new self("Unsupported alias entity type: {$entityType}.");
    }

    public static function missingEntity(string $entityType): self
    {
        return new self("Both {$entityType} identities must exist before an alias is created.");
    }

    public static function cycle(string $entityType, string $entityId): self
    {
        return new self("The {$entityType} alias for {$entityId} would create a cycle.");
    }
}
