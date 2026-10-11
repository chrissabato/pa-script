<?php

declare(strict_types=1);

namespace App\Templates;

/** A template editor text that doesn't parse, with the line to point at. */
class TemplateSyntaxError extends \RuntimeException
{
    /** @var int */
    private $templateLine;

    public function __construct(string $message, int $line)
    {
        parent::__construct($message);
        $this->templateLine = $line;
    }

    public function getTemplateLine(): int
    {
        return $this->templateLine;
    }
}
