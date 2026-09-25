<?php

namespace App\Domains\Identity\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

abstract readonly class PublicEntityCode implements Stringable
{
    final protected function __construct(public string $value) {}

    protected static function normalize(string $source, string $entityPrefix): string
    {
        $normalized = Str::upper(trim($source));
        $pattern = '/^STP-'.$entityPrefix.'-[0-9A-HJKMNP-TV-Z]{8}$/';

        if (preg_match($pattern, $normalized) !== 1) {
            throw new InvalidArgumentException('Invalid STUMPS public entity code.');
        }

        return $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
