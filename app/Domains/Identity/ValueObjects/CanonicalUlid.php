<?php

namespace App\Domains\Identity\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

final readonly class CanonicalUlid implements Stringable
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self((string) Str::ulid());
    }

    public static function fromString(string $source): self
    {
        $normalized = Str::upper(trim($source));

        if (! Str::isUlid($normalized)) {
            throw new InvalidArgumentException('Invalid canonical ULID.');
        }

        return new self($normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
