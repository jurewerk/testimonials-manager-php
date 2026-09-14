<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * The seam a real provider would be plugged into later. Swapping in a live
 * OpenAI/Gemini/Claude client means implementing this interface and registering
 * it in AiProviderFactory — nothing else in the application changes.
 */
interface AiProvider
{
    public function key(): string;

    public function label(): string;

    public function translate(string $text, string $countryCode): string;

    public function generateAuthorName(string $countryCode, string $gender): string;
}
