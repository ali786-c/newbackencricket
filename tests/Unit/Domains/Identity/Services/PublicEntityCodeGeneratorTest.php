<?php

namespace Tests\Unit\Domains\Identity\Services;

use App\Domains\Identity\Services\PublicEntityCodeGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PublicEntityCodeGeneratorTest extends TestCase
{
    public function test_generates_typed_player_and_team_codes(): void
    {
        $bodies = ['7K4M9Q2D', '9X2ABC6R'];
        $generator = new PublicEntityCodeGenerator(function () use (&$bodies): string {
            return array_shift($bodies);
        });

        $playerCode = $generator->playerCode(fn (string $code): bool => false);
        $teamCode = $generator->teamCode(fn (string $code): bool => false);

        $this->assertSame('STP-P-7K4M9Q2D', $playerCode->value);
        $this->assertSame('STP-T-9X2ABC6R', $teamCode->value);
    }

    public function test_regenerates_a_code_after_a_collision(): void
    {
        $bodies = ['7K4M9Q2D', '9X2ABC6R'];
        $generator = new PublicEntityCodeGenerator(function () use (&$bodies): string {
            return array_shift($bodies);
        }, maxAttempts: 2);

        $code = $generator->playerCode(fn (string $candidate): bool => $candidate === 'STP-P-7K4M9Q2D');

        $this->assertSame('STP-P-9X2ABC6R', $code->value);
    }

    public function test_fails_after_the_bounded_number_of_collisions(): void
    {
        $generator = new PublicEntityCodeGenerator(fn (): string => '7K4M9Q2D', maxAttempts: 2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('after 2 attempts');

        $generator->teamCode(fn (string $code): bool => true);
    }
}
