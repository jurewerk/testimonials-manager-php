<?php

declare(strict_types=1);

/**
 * Test suite.
 *
 *   php tests/run.php
 *
 * Database-backed tests run against a dedicated database whose name must end in
 * "_test"; the runner refuses anything else so a stray configuration can never
 * drop application data.
 */

require dirname(__DIR__).'/src/autoload.php';
require __DIR__.'/Harness.php';

use App\App;
use App\Service\LandingSyncService;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Router;
use App\Support\Validator;
use App\Support\ValidationException;

$config = require dirname(__DIR__).'/config/config.php';

// ---------------------------------------------------------------------------
// Unit tests: no database needed
// ---------------------------------------------------------------------------

Harness::test('validator enforces required fields', function () {
    Harness::assertThrows(
        fn () => Validator::make(['comment' => 'x'], ['author_name' => 'required|string'])->validate(),
        ValidationException::class
    );
});

Harness::test('validator bounds the comment length', function () {
    $e = Harness::assertThrows(
        fn () => Validator::make(['comment' => str_repeat('x', 2001)], ['comment' => 'required|string|max:2000'])->validate(),
        ValidationException::class
    );
    Harness::assertTrue(isset($e->errors()['comment']));
});

Harness::test('validator accepts http and https links only', function () {
    $rules = ['link' => 'nullable|string|url'];

    Harness::assertSame('https://example.com/a', Validator::make(['link' => 'https://example.com/a'], $rules)->validate()['link']);
    Harness::assertSame(null, Validator::make(['link' => ''], $rules)->validate()['link']);

    foreach (['not-a-url', 'javascript:alert(1)', 'ftp://example.com'] as $bad) {
        Harness::assertThrows(fn () => Validator::make(['link' => $bad], $rules)->validate(), ValidationException::class);
    }
});

Harness::test('validator restricts rating and gender to the allowed sets', function () {
    Harness::assertThrows(fn () => Validator::make(['rating' => 9], ['rating' => 'nullable|integer|between:1,5'])->validate(), ValidationException::class);
    Harness::assertThrows(fn () => Validator::make(['gender' => 'alien'], ['gender' => 'required|in:male,female,unisex'])->validate(), ValidationException::class);
    Harness::assertSame('female', Validator::make(['gender' => 'female'], ['gender' => 'required|in:male,female,unisex'])->validate()['gender']);
});

Harness::test('validator ignores fields that were not declared', function () {
    $result = Validator::make(['author_name' => 'Ana', 'is_admin' => 1], ['author_name' => 'required|string'])->validate();
    Harness::assertSame(['author_name' => 'Ana'], $result);
});

/** Builds a Request with a specific server layout. */
function requestFor(string $scriptName, string $requestUri, array $server = []): Request
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['CONTENT_TYPE'] = '';
    unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME'], $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

    foreach ($server as $key => $value) {
        $_SERVER[$key] = $value;
    }
    $_GET = [];
    $_POST = [];
    $_FILES = [];

    return new Request();
}

Harness::test('base path is resolved for every supported server layout', function () {
    $cases = [
        // script name,                              request uri,                           base,               path
        ['/index.php', '/products/1', '', '/products/1'],
        ['/public/index.php', '/products/1', '', '/products/1'],
        ['/testimonials-manager/public/index.php', '/testimonials-manager/products/1', '/testimonials-manager', '/products/1'],
        ['/testimonials-manager/public/index.php', '/testimonials-manager/public/api/products', '/testimonials-manager/public', '/api/products'],
        ['/testimonials-manager/public/index.php', '/testimonials-manager/', '/testimonials-manager', '/'],
    ];

    foreach ($cases as [$script, $uri, $base, $path]) {
        $request = requestFor($script, $uri);
        Harness::assertSame($base, $request->basePath, "base for $uri");
        Harness::assertSame($path, $request->path, "path for $uri");
    }
});

Harness::test('the base URL is absolute so off-site consumers resolve image links', function () {
    // A landing page on another host receives these URLs; a root-relative path
    // would resolve against that host instead of this application.
    $cases = [
        // server overrides,                                              script, uri,                 expected base URL
        [['HTTP_HOST' => 'admin.example.com'], '/index.php', '/api/x', 'http://admin.example.com'],
        [['HTTP_HOST' => 'admin.example.com', 'HTTPS' => 'on'], '/index.php', '/api/x', 'https://admin.example.com'],
        [['HTTP_HOST' => 'admin.example.com', 'HTTPS' => 'off'], '/index.php', '/api/x', 'http://admin.example.com'],
        [['HTTP_HOST' => 'admin.example.com:8080'], '/index.php', '/api/x', 'http://admin.example.com:8080'],
        // TLS terminated at a proxy.
        [['HTTP_HOST' => 'example.com', 'HTTP_X_FORWARDED_PROTO' => 'https'], '/index.php', '/api/x', 'https://example.com'],
        // Subdirectory install keeps its prefix.
        [['HTTP_HOST' => 'localhost'], '/testimonials-manager/public/index.php', '/testimonials-manager/api/x', 'http://localhost/testimonials-manager'],
    ];

    foreach ($cases as [$server, $script, $uri, $expected]) {
        Harness::assertSame($expected, requestFor($script, $uri, $server)->baseUrl, "base URL for $uri");
    }

    // A forged or missing Host is not echoed back into generated URLs.
    foreach (['evil.com/../x', 'a b', ''] as $bad) {
        Harness::assertSame('', requestFor('/index.php', '/api/x', ['HTTP_HOST' => $bad])->baseUrl, "rejects host \"$bad\"");
    }
});

Harness::test('HEAD is routed as GET so caches and proxies can probe an image', function () {
    $router = new Router();
    $router->get('/api/images/{path}', fn ($r, $p) => $p);
    $router->post('/api/testimonials/{id}/images', fn ($r, $p) => $p);

    [$handler, $params] = $router->match('HEAD', '/api/images/abc.webp');
    Harness::assertSame(['path' => 'abc.webp'], $params);

    // A route that has no GET is still refused, rather than answered.
    Harness::assertThrows(fn () => $router->match('HEAD', '/api/testimonials/1/images'), HttpException::class);
    Harness::assertSame(null, $router->match('HEAD', '/api/nothing'));
});

Harness::test('thumbnails are cropped to exactly the configured size, full images only scaled down', function () use ($config) {
    $service = (new ReflectionClass(\App\Service\ImageService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty(\App\Service\ImageService::class, 'config');
    $property->setAccessible(true);
    $property->setValue($service, $config['uploads']);

    $resize = new ReflectionMethod(\App\Service\ImageService::class, 'resize');
    $resize->setAccessible(true);

    foreach ([[1600, 400], [400, 1600], [640, 480], [100, 100], [2400, 1800]] as [$w, $h]) {
        $source = imagecreatetruecolor($w, $h);
        // A red block in the middle: a crop anchored anywhere but the centre
        // would miss it.
        imagefilledrectangle($source, 0, 0, $w - 1, $h - 1, imagecolorallocate($source, 10, 20, 30));
        imagefilledrectangle($source, (int) ($w * 0.25), (int) ($h * 0.25), (int) ($w * 0.75), (int) ($h * 0.75), imagecolorallocate($source, 200, 50, 50));

        $thumb = $resize->invoke($service, $source, 320, 240, true);
        Harness::assertSame(320, imagesx($thumb), "thumb width for {$w}x{$h}");
        Harness::assertSame(240, imagesy($thumb), "thumb height for {$w}x{$h}");

        $centre = imagecolorsforindex($thumb, imagecolorat($thumb, 160, 120));
        Harness::assertSame(200, $centre['red'], "thumb is centred for {$w}x{$h}");

        // Without cropping the whole image is kept and never enlarged.
        $full = $resize->invoke($service, $source, 2000, 2000, false);
        Harness::assertTrue(imagesx($full) <= 2000 && imagesy($full) <= 2000, "full fits the box for {$w}x{$h}");
        Harness::assertSame(
            round($w / $h, 2),
            round(imagesx($full) / imagesy($full), 2),
            "full keeps the aspect ratio for {$w}x{$h}"
        );

        imagedestroy($source);
        imagedestroy($thumb);
        imagedestroy($full);
    }
});

Harness::test('mock AI providers return the country-prefixed text and never call out', function () {
    $factory = new \App\Ai\AiProviderFactory();

    Harness::assertCount(3, $factory->list());

    foreach (['openai', 'claude', 'gemini'] as $key) {
        $provider = $factory->make($key);
        Harness::assertSame('[SI] This is my translated text', $provider->translate('This is my translated text', 'SI'));
        Harness::assertSame('[IT] This is my translated text', $provider->translate('This is my translated text', 'it'));
        Harness::assertSame('[SI] Janez Novak', $provider->generateAuthorName('SI', 'male'));
        Harness::assertSame('[IT] Mario Rossi', $provider->generateAuthorName('IT', 'male'));
    }

    // An unknown country falls back to the English fixtures rather than failing.
    Harness::assertSame('[ZZ] James Smith', $factory->make('openai')->generateAuthorName('ZZ', 'male'));
    Harness::assertThrows(fn () => $factory->make('nope'), ValidationException::class);
});

// ---------------------------------------------------------------------------
// Database-backed tests
// ---------------------------------------------------------------------------

$testDb = getenv('TEST_DB_NAME') ?: 'testimonials_test';

if (! str_ends_with($testDb, '_test')) {
    fwrite(STDERR, "Refusing to run: the test database name must end in \"_test\".\n");
    exit(1);
}

$config['db']['name'] = $testDb;

try {
    $app = new App($config);
    $app->db()->scalar('SELECT 1');
    $hasDb = true;
} catch (\Throwable $e) {
    $hasDb = false;
    fwrite(STDERR, "\nSkipping database tests: {$e->getMessage()}\n");
    fwrite(STDERR, "Create it with:\n  mysql -e 'CREATE DATABASE $testDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'\n");
    fwrite(STDERR, "  mysql $testDb < schema.sql\n\n");
}

if ($hasDb) {
    $db = $app->db();

    $reset = function () use ($db, $app) {
        $db->execute('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['activity_logs', 'testimonial_images', 'testimonials', 'landings', 'products', 'users'] as $table) {
            $db->execute("TRUNCATE TABLE `$table`");
        }
        $db->execute('SET FOREIGN_KEY_CHECKS = 1');

        return $app->users()->create('Tester', 'tester@example.test', 'test-password-123');
    };

    /** Creates a product with an EN master and one localized landing. */
    $fixture = function () use ($app, $db) {
        $productId = $app->products()->findOrCreateBySku('KIT-01');

        $en = $db->insert(
            'INSERT INTO landings (external_id, product_id, country_code, is_master, title, landing_url) VALUES (?, ?, ?, 1, ?, ?)',
            ['ext-en', $productId, 'EN', 'Kit', 'https://example.com/en/kit']
        );
        $si = $db->insert(
            'INSERT INTO landings (external_id, product_id, country_code, is_master, title, landing_url) VALUES (?, ?, ?, 0, ?, ?)',
            ['ext-si', $productId, 'SI', 'Komplet', 'https://example.com/si/kit']
        );

        return [$productId, $en, $si];
    };

    Harness::test('password hashing uses password_hash and rejects a wrong password', function () use ($reset, $app, $db) {
        $reset();
        $row = $db->selectOne('SELECT password_hash FROM users WHERE email = ?', ['tester@example.test']);

        Harness::assertTrue(str_starts_with($row['password_hash'], '$2y$') || str_starts_with($row['password_hash'], '$argon'), 'Password must be hashed');
        Harness::assertTrue(password_verify('test-password-123', $row['password_hash']));
        Harness::assertSame(null, $app->auth()->attempt('tester@example.test', 'wrong-password'));
    });

    Harness::test('a country inherits English only while it has no local rows of its own', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [, $en, $si] = $fixture();

        $app->testimonials()->create([
            'landing_id' => $en, 'author_name' => 'Emma', 'comment' => 'Great', 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'female', 'is_active' => true,
            'sort_order' => 0, 'created_by' => $userId,
        ]);

        $siLanding = $app->landings()->find($si);
        Harness::assertTrue($app->fallback()->resolve($siLanding)['inherited'], 'SI should inherit');

        // An INACTIVE local row still stops the fallback: showing nothing must be possible.
        $app->testimonials()->create([
            'landing_id' => $si, 'author_name' => 'Ana', 'comment' => 'Super', 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 4, 'gender' => 'female', 'is_active' => false,
            'sort_order' => 0, 'created_by' => $userId,
        ]);

        Harness::assertTrue($app->fallback()->resolve($app->landings()->find($si))['inherited'] === false, 'SI must stop inheriting');
    });

    Harness::test('country overview counts come back without a query per country', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [$productId, $en, $si] = $fixture();

        foreach ([true, false] as $i => $active) {
            $app->testimonials()->create([
                'landing_id' => $en, 'author_name' => 'A'.$i, 'comment' => 'c', 'link' => null,
                'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'unisex', 'is_active' => $active,
                'sort_order' => $i, 'created_by' => $userId,
            ]);
        }

        $rows = $app->landings()->forProductWithCounts($productId);
        $byCountry = [];
        foreach ($rows as $row) {
            $byCountry[$row['country_code']] = $row;
        }

        Harness::assertSame(2, $byCountry['EN']['own_count']);
        Harness::assertSame(1, $byCountry['EN']['own_active_count']);
        Harness::assertSame(0, $byCountry['SI']['own_count']);
        // SI inherits only the master's ACTIVE row.
        Harness::assertSame(1, $byCountry['SI']['effective_count']);
        Harness::assertTrue($byCountry['SI']['inherits']);
    });

    Harness::test('ordering rejects a partial list and a stale version, then writes gapless positions', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [, $en] = $fixture();

        $ids = [];
        foreach (range(0, 3) as $i) {
            $ids[] = $app->testimonials()->create([
                'landing_id' => $en, 'author_name' => 'A'.$i, 'comment' => 'c', 'link' => null,
                'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'unisex', 'is_active' => true,
                'sort_order' => $i, 'created_by' => $userId,
            ]);
        }

        $landing = $app->landings()->find($en);

        Harness::assertThrows(
            fn () => $app->ordering()->reorder($landing, [$ids[0], $ids[1]], $landing['version']),
            ValidationException::class
        );

        Harness::assertThrows(
            fn () => $app->ordering()->reorder($landing, array_reverse($ids), 999),
            HttpException::class,
            'another session'
        );

        $app->ordering()->reorder($landing, array_reverse($ids), $landing['version']);

        $ordered = array_map('intval', array_column($app->testimonials()->allForLanding($en), 'id'));
        Harness::assertSame(array_reverse($ids), $ordered, 'Order must be reversed');

        $positions = array_map('intval', array_column($app->testimonials()->allForLanding($en), 'sort_order'));
        Harness::assertSame([0, 1, 2, 3], $positions, 'Positions must be gapless and zero-based');
    });

    Harness::test('copying between countries creates independent rows', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [, $en, $si] = $fixture();

        $app->testimonials()->create([
            'landing_id' => $en, 'author_name' => 'Emma', 'comment' => 'Great product', 'link' => 'https://example.com/x',
            'rating_mode' => 'random', 'rating' => null, 'gender' => 'female', 'is_active' => true,
            'sort_order' => 0, 'created_by' => $userId,
        ]);

        $destination = $app->landings()->find($si);
        $result = $app->copies()->copy($destination, $app->landings()->find($en), 'append', $destination['version'], $userId);

        Harness::assertSame(1, $result['copied']);

        $copies = $app->testimonials()->allForLanding($si);
        Harness::assertCount(1, $copies);
        Harness::assertSame('Great product', $copies[0]['comment']);
        Harness::assertSame('random', $copies[0]['rating_mode']);

        // Editing the copy must not touch the source.
        $app->testimonials()->update((int) $copies[0]['id'], [
            'author_name' => 'Ana', 'comment' => 'Odlično', 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 4, 'gender' => 'female', 'is_active' => true,
        ], $userId);

        $source = $app->testimonials()->allForLanding($en);
        Harness::assertSame('Emma', $source[0]['author_name'], 'The source must be untouched');
    });

    Harness::test('the "only when empty" strategy does nothing when rows already exist', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [, $en, $si] = $fixture();

        foreach ([$en, $si] as $landing) {
            $app->testimonials()->create([
                'landing_id' => $landing, 'author_name' => 'X', 'comment' => 'c', 'link' => null,
                'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'unisex', 'is_active' => true,
                'sort_order' => 0, 'created_by' => $userId,
            ]);
        }

        $destination = $app->landings()->find($si);
        $result = $app->copies()->copy($destination, $app->landings()->find($en), 'empty', $destination['version'], $userId);

        Harness::assertSame(0, $result['copied']);
        Harness::assertCount(1, $app->testimonials()->allForLanding($si));
    });

    Harness::test('translation copies run every row through the chosen mock provider', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [, $en, $si] = $fixture();

        $app->testimonials()->create([
            'landing_id' => $en, 'author_name' => 'Emma Wilson', 'comment' => 'This is my translated text', 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'female', 'is_active' => true,
            'sort_order' => 0, 'created_by' => $userId,
        ]);

        $destination = $app->landings()->find($si);
        $app->copies()->copy(
            $destination, $app->landings()->find($en), 'append', $destination['version'], $userId,
            $app->ai()->make('claude'), true
        );

        $rows = $app->testimonials()->allForLanding($si);
        Harness::assertSame('[SI] This is my translated text', $rows[0]['comment']);
        Harness::assertSame('[SI] Ana Novak', $rows[0]['author_name']);
    });

    Harness::test('a re-import updates changed fields and never detaches testimonials', function () use ($reset, $app, $db, $config) {
        $userId = $reset();

        // A stub client standing in for the provider.
        $pages = [[
            ['id' => '900', 'parent_sku' => 'kit', 'country' => 'EN', 'title' => 'Kit', 'url' => 'https://example.com/en/kit', 'description' => 'First'],
            ['id' => '901', 'parent_sku' => 'kit', 'country' => 'SI', 'title' => 'Komplet', 'url' => 'https://example.com/si/kit'],
        ]];

        $client = new class($pages) extends \App\Service\LandingApiClient {
            public array $pages;

            public function __construct(array $pages)
            {
                $this->pages = $pages;
            }

            public function fetchPage(int $limit, int $offset, array $filters = []): array
            {
                return $this->pages[0] ?? [];
            }
        };

        $sync = new LandingSyncService($db, $client, $app->products(), $app->landings(), 500);

        $first = $sync->sync();
        Harness::assertSame(2, $first['created']);

        $landing = $app->landings()->findByExternalId('900');
        $landingId = $landing['id'];

        $testimonialId = $app->testimonials()->create([
            'landing_id' => $landingId, 'author_name' => 'Emma', 'comment' => 'Great', 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'female', 'is_active' => true,
            'sort_order' => 0, 'created_by' => $userId,
        ]);

        // Second run, unchanged.
        $second = $sync->sync();
        Harness::assertSame(0, $second['created']);
        Harness::assertSame(2, $second['unchanged']);

        // Third run with a changed description.
        $client->pages[0][0]['description'] = 'Second';
        $third = $sync->sync();
        Harness::assertSame(1, $third['updated']);

        $after = $app->landings()->findByExternalId('900');
        Harness::assertSame($landingId, $after['id'], 'The landing id must survive a re-import');
        Harness::assertSame('Second', $after['description']);

        $testimonial = $app->testimonials()->find($testimonialId);
        Harness::assertSame($landingId, $testimonial['landing_id'], 'The testimonial must keep its landing');
    });

    Harness::test('a duplicate identity from the provider aborts the whole import', function () use ($reset, $app, $db) {
        $reset();

        $client = new class extends \App\Service\LandingApiClient {
            public function __construct() {}

            public function fetchPage(int $limit, int $offset, array $filters = []): array
            {
                return [
                    ['id' => '1', 'parent_sku' => 'a', 'country' => 'EN', 'title' => 'A', 'url' => 'https://example.com/a'],
                    ['id' => '1', 'parent_sku' => 'b', 'country' => 'EN', 'title' => 'B', 'url' => 'https://example.com/b'],
                ];
            }
        };

        $sync = new LandingSyncService($db, $client, $app->products(), $app->landings(), 500);

        Harness::assertThrows(fn () => $sync->sync(), HttpException::class, 'more than once');
        Harness::assertSame(0, (int) $db->scalar('SELECT COUNT(*) FROM landings'), 'Nothing may be written');
    });

    Harness::test('an invalid provider URL aborts the import before anything is written', function () use ($reset, $app, $db) {
        $reset();

        $client = new class extends \App\Service\LandingApiClient {
            public function __construct() {}

            public function fetchPage(int $limit, int $offset, array $filters = []): array
            {
                return [['id' => '1', 'parent_sku' => 'a', 'country' => 'EN', 'title' => 'A', 'url' => 'not-a-url']];
            }
        };

        $sync = new LandingSyncService($db, $client, $app->products(), $app->landings(), 500);

        Harness::assertThrows(fn () => $sync->sync(), HttpException::class, 'invalid URL');
        Harness::assertSame(0, (int) $db->scalar('SELECT COUNT(*) FROM landings'));
    });

    Harness::test('a random rating is resolved to 4 or 5 on every read', function () use ($reset, $app) {
        $reset();

        $seen = [];
        for ($i = 0; $i < 60; $i++) {
            $seen[$app->testimonials()->presentPublic([
                'id' => 1, 'author_name' => 'A', 'comment' => 'c', 'link' => null,
                'rating_mode' => 'random', 'rating' => null,
            ])['rating']] = true;
        }

        ksort($seen);
        Harness::assertSame([4, 5], array_keys($seen), 'Random ratings must only ever be 4 or 5');
    });

    Harness::test('the public shape never leaks internal fields', function () use ($app) {
        $public = $app->testimonials()->presentPublic([
            'id' => 1, 'author_name' => 'A', 'comment' => 'c', 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 5,
        ]);

        foreach (['created_by', 'updated_by', 'version', 'is_active', 'sort_order'] as $field) {
            Harness::assertTrue(! array_key_exists($field, $public), "Public output must not expose $field");
        }
    });

    Harness::test('utf8mb4 content survives a database round trip', function () use ($reset, $fixture, $app) {
        $userId = $reset();
        [, $en] = $fixture();

        $text = 'Кирилица, Ελληνικά, Türkçe, čšž — 😀';

        $id = $app->testimonials()->create([
            'landing_id' => $en, 'author_name' => 'Живко Šmid', 'comment' => $text, 'link' => null,
            'rating_mode' => 'fixed', 'rating' => 5, 'gender' => 'male', 'is_active' => true,
            'sort_order' => 0, 'created_by' => $userId,
        ]);

        $row = $app->testimonials()->find($id);
        Harness::assertSame($text, $row['comment']);
        Harness::assertSame('Живко Šmid', $row['author_name']);
    });
}

exit(Harness::run());
