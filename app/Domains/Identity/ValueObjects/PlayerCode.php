<?php

namespace App\Domains\Identity\ValueObjects;

final readonly class PlayerCode extends PublicEntityCode
{
    public static function fromString(string $source): self
    {
        return new self(self::normalize($source, 'P'));
    }
}
