<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Mock adapter. A real client would replace the bodies inherited from
 * MockProvider without touching any calling code.
 */
class OpenAiProvider extends MockProvider
{
    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI · mock';
    }
}
