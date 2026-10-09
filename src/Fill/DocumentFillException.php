<?php

declare(strict_types=1);

namespace App\Fill;

/** A document that can't be read, or a Gemini call that failed; the message is shown to the user. */
class DocumentFillException extends \RuntimeException
{
}
