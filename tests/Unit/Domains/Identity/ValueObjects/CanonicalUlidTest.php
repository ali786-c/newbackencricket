<?php

namespace Tests\Unit\Domains\Identity\ValueObjects;

use App\Domains\Identity\ValueObjects\CanonicalUlid;
use InvalidArgumentException;
use Tests\TestCase;

class CanonicalUlidTest extends TestCase
{
    public function test_normalizes_a_valid_lowercase_ulid(): void
    {
        $ulid = CanonicalUlid::fromString('01k5a000000000000000000001');

        $this->assertSame('01K5A000000000000000000001', $ulid->value);
        $this->assertSame($ulid->value, (string) $ulid);
    }

    public function test_generates_a_canonical_ulid(): void
    {
        $ulid = CanonicalUlid::generate();

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid->value);
        $this->assertSame($ulid->value, CanonicalUlid::fromString($ulid->value)->value);
    }

    public function test_rejects_an_invalid_ulid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CanonicalUlid::fromString('01K5A00000000000000000000I');
    }
}
