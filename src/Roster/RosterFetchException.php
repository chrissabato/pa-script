<?php

declare(strict_types=1);

namespace App\Roster;

/** A roster page couldn't be fetched; the message is safe to show the user. */
class RosterFetchException extends \RuntimeException
{
}
