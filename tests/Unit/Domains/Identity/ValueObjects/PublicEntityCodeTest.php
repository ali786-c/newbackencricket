<?php

namespace Tests\Unit\Domains\Identity\ValueObjects;

use App\Domains\Identity\ValueObjects\PlayerCode;
use App\Domains\Identity\ValueObjects\TeamCode;
use InvalidArgumentException;
use Tests\TestCase;

class PublicEntityCodeTest extends TestCase
{
    public function test_normalizes_valid_player_and_team_codes(): void
    {
        $playerCode = PlayerCode::fromString(' stp-p-7k4m9q2d ');
        $teamCode = TeamCode::fromString('stp-t-9x2abc6r');

        $this->assertSame('STP-P-7K4M9Q2D', $playerCode->value);
        $this->assertSame('STP-T-9X2ABC6R', $teamCode->value);
        $this->assertSame($playerCode->value, (string) $playerCode);
        $this->assertSame($teamCode->value, (string) $teamCode);
    }

    public function test_rejects_the_wrong_entity_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PlayerCode::fromString('STP-T-9X2ABC6R');
    }

    public function test_rejects_ambiguous_crockford_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamCode::fromString('STP-T-9X2ABO6R');
    }
}
