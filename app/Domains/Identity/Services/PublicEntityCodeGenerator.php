<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\ValueObjects\PlayerCode;
use App\Domains\Identity\ValueObjects\PublicEntityCode;
use App\Domains\Identity\ValueObjects\TeamCode;
use Closure;
use RuntimeException;

class PublicEntityCodeGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @var Closure(): string */
    private readonly Closure $bodyGenerator;

    /** @param null|Closure(): string $bodyGenerator */
    public function __construct(?Closure $bodyGenerator = null, private readonly int $maxAttempts = 10)
    {
        if ($maxAttempts < 1) {
            throw new RuntimeException('Public-code generation requires at least one attempt.');
        }

        $this->bodyGenerator = $bodyGenerator ?? fn (): string => $this->randomBody();
    }

    /** @param callable(string): bool $isTaken */
    public function playerCode(callable $isTaken): PlayerCode
    {
        /** @var PlayerCode $code */
        $code = $this->generate('P', $isTaken, PlayerCode::fromString(...));

        return $code;
    }

    /** @param callable(string): bool $isTaken */
    public function teamCode(callable $isTaken): TeamCode
    {
        /** @var TeamCode $code */
        $code = $this->generate('T', $isTaken, TeamCode::fromString(...));

        return $code;
    }

    /**
     * @param  callable(string): bool  $isTaken
     * @param  callable(string): PublicEntityCode  $factory
     */
    private function generate(string $entityPrefix, callable $isTaken, callable $factory): PublicEntityCode
    {
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            $body = ($this->bodyGenerator)();
            $code = "STP-{$entityPrefix}-{$body}";
            $value = $factory($code);

            if (! $isTaken($value->value)) {
                return $value;
            }
        }

        throw new RuntimeException("Unable to allocate a unique {$entityPrefix} public code after {$this->maxAttempts} attempts.");
    }

    private function randomBody(): string
    {
        $body = '';
        $lastIndex = strlen(self::ALPHABET) - 1;

        for ($position = 0; $position < 8; $position++) {
            $body .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $body;
    }
}
