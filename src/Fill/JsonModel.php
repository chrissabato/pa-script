<?php

declare(strict_types=1);

namespace App\Fill;

/** A model that answers a prompt with JSON matching a schema (GeminiClient; a fake in tests). */
interface JsonModel
{
    /**
     * @param array<int, array<string, mixed>> $parts Gemini content parts (text / inline_data)
     * @param array<string, mixed> $schema Gemini responseSchema
     * @return array<string, mixed>
     * @throws DocumentFillException
     */
    public function generateJson(array $parts, array $schema): array;
}
