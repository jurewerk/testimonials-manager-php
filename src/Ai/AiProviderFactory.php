<?php

declare(strict_types=1);

namespace App\Ai;

use App\Support\ValidationException;

class AiProviderFactory
{
    /** @var array<string, AiProvider> */
    private array $providers = [];

    public function __construct()
    {
        foreach ([new OpenAiProvider(), new ClaudeProvider(), new GeminiProvider()] as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function make(string $key): AiProvider
    {
        if (! isset($this->providers[$key])) {
            throw new ValidationException(['provider' => ['Unknown provider.']]);
        }

        return $this->providers[$key];
    }

    public function list(): array
    {
        return array_map(
            static fn (AiProvider $p) => ['key' => $p->key(), 'label' => $p->label()],
            array_values($this->providers)
        );
    }
}
