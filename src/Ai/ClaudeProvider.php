<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Mock adapter. A real client would replace the bodies inherited from
 * MockProvider without touching any calling code.
 */
class ClaudeProvider extends MockProvider
{
    public function key(): string
    {
        return 'claude';
    }

    public function label(): string
    {
        return 'Claude · mock';
    }
}
