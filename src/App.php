<?php

declare(strict_types=1);

namespace App;

use App\Ai\AiProviderFactory;
use App\Repository\ActivityRepository;
use App\Repository\ImageRepository;
use App\Repository\LandingRepository;
use App\Repository\ProductRepository;
use App\Repository\TestimonialRepository;
use App\Repository\UserRepository;
use App\Service\CopyService;
use App\Service\FallbackResolver;
use App\Service\ImageService;
use App\Service\LandingApiClient;
use App\Service\LandingSyncService;
use App\Service\OrderingService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Session;

/**
 * Tiny service container. Everything is constructed once, lazily, and wired by
 * hand — explicit enough to read top to bottom.
 */
class App
{
    public array $config;

    private array $instances = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    private function once(string $key, callable $factory)
    {
        return $this->instances[$key] ??= $factory();
    }

    public function db(): Database
    {
        return $this->once('db', fn () => new Database($this->config['db']));
    }

    public function session(): Session
    {
        return $this->once('session', fn () => new Session($this->config['session']));
    }

    public function csrf(): Csrf
    {
        return $this->once('csrf', fn () => new Csrf($this->session()));
    }

    public function users(): UserRepository
    {
        return $this->once('users', fn () => new UserRepository($this->db()));
    }

    public function auth(): Auth
    {
        return $this->once('auth', fn () => new Auth($this->session(), $this->users()));
    }

    public function products(): ProductRepository
    {
        return $this->once('products', fn () => new ProductRepository($this->db()));
    }

    public function landings(): LandingRepository
    {
        return $this->once('landings', fn () => new LandingRepository($this->db()));
    }

    public function images(): ImageRepository
    {
        return $this->once('images', fn () => new ImageRepository($this->db()));
    }

    public function testimonials(): TestimonialRepository
    {
        return $this->once('testimonials', fn () => new TestimonialRepository($this->db(), $this->images()));
    }

    public function activity(): ActivityRepository
    {
        return $this->once('activity', fn () => new ActivityRepository($this->db()));
    }

    public function imageService(): ImageService
    {
        return $this->once('imageService', fn () => new ImageService($this->images(), $this->config['uploads']));
    }

    public function fallback(): FallbackResolver
    {
        return $this->once('fallback', fn () => new FallbackResolver($this->landings(), $this->testimonials()));
    }

    public function ordering(): OrderingService
    {
        return $this->once('ordering', fn () => new OrderingService($this->db(), $this->landings(), $this->testimonials()));
    }

    public function copies(): CopyService
    {
        return $this->once('copies', fn () => new CopyService(
            $this->db(), $this->landings(), $this->testimonials(), $this->images(), $this->imageService(), $this->activity()
        ));
    }

    public function ai(): AiProviderFactory
    {
        return $this->once('ai', fn () => new AiProviderFactory());
    }

    public function sync(): LandingSyncService
    {
        return $this->once('sync', fn () => new LandingSyncService(
            $this->db(),
            new LandingApiClient($this->config['landings_api']),
            $this->products(),
            $this->landings(),
            $this->config['landings_api']['page_size']
        ));
    }
}
