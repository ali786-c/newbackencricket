<?php

namespace App\Domains\Teams\Exceptions;

use RuntimeException;

class OverlappingTeamMembershipException extends RuntimeException
{
    public function __construct(public readonly string $teamId, public readonly string $playerId)
    {
        parent::__construct('The player already has an overlapping membership interval for this team.');
    }
}
