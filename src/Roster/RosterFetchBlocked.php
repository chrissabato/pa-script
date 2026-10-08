<?php

declare(strict_types=1);

namespace App\Roster;

/**
 * The site refused a server-side request (bot check / WAF), as opposed to
 * a bad URL — the UI offers the in-browser fallbacks instead.
 */
class RosterFetchBlocked extends RosterFetchException
{
}
