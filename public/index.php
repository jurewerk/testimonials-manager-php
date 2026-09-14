<?php

declare(strict_types=1);

/**
 * Single entry point. Apache rewrites every request that is not a real file
 * into this script; API routes return JSON and everything else returns the
 * application shell.
 */

use App\App;
use App\Controller\AuthController;
use App\Controller\CopyController;
use App\Controller\ImageController;
use App\Controller\ProductController;
use App\Controller\PublicController;
use App\Controller\SyncController;
use App\Controller\TestimonialController;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;

require dirname(__DIR__).'/src/autoload.php';

$config = require dirname(__DIR__).'/config/config.php';

ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$request = new Request();
$app = new App($config);
$router = new Router();

$auth = new AuthController($app);
$products = new ProductController($app);
$testimonials = new TestimonialController($app);
$images = new ImageController($app);
$sync = new SyncController($app);
$copies = new CopyController($app);
$public = new PublicController($app);

// ---- Session and authentication ----
$router->post('/api/login', fn ($r, $p) => $auth->login($r));
$router->post('/api/logout', fn ($r, $p) => $auth->logout());
$router->get('/api/user', fn ($r, $p) => $auth->me());

// ---- Products and countries ----
$router->get('/api/products', fn ($r, $p) => $products->index($r));
$router->get('/api/products/{id}', fn ($r, $p) => $products->show($r, $p));
$router->get('/api/products/{id}/landings', fn ($r, $p) => $products->landings($r, $p));

// ---- Landing synchronisation ----
$router->post('/api/landings/sync', fn ($r, $p) => $sync->sync($r));

// ---- Testimonials ----
$router->get('/api/landings/{id}/testimonials', fn ($r, $p) => $testimonials->index($r, $p));
$router->post('/api/landings/{id}/testimonials', fn ($r, $p) => $testimonials->store($r, $p));
$router->post('/api/landings/{id}/testimonials/reorder', fn ($r, $p) => $testimonials->reorder($r, $p));
$router->post('/api/landings/{id}/testimonials/bulk-action', fn ($r, $p) => $testimonials->bulk($r, $p));
$router->post('/api/landings/{id}/copy-testimonials', fn ($r, $p) => $copies->copy($r, $p));
$router->post('/api/landings/{id}/translate-testimonials', fn ($r, $p) => $copies->translate($r, $p));

$router->get('/api/testimonials/{id}', fn ($r, $p) => $testimonials->show($r, $p));
$router->patch('/api/testimonials/{id}', fn ($r, $p) => $testimonials->update($r, $p));
$router->delete('/api/testimonials/{id}', fn ($r, $p) => $testimonials->destroy($r, $p));
$router->get('/api/testimonials/{id}/activity', fn ($r, $p) => $testimonials->activity($r, $p));
$router->post('/api/testimonials/{id}/generate-author-name', fn ($r, $p) => $testimonials->generateName($r, $p));

// ---- Images ----
$router->post('/api/testimonials/{id}/images', fn ($r, $p) => $images->store($r, $p));
$router->post('/api/testimonials/{id}/images/reorder', fn ($r, $p) => $images->reorder($r, $p));
$router->delete('/api/testimonial-images/{id}', fn ($r, $p) => $images->destroy($r, $p));
$router->get('/api/images/{path}', fn ($r, $p) => $images->serve($r, $p));

// ---- Misc ----
$router->get('/api/ai-providers', fn ($r, $p) => $copies->providers());
$router->get('/api/public/landings/{external_id}/testimonials', fn ($r, $p) => $public->testimonials($r, $p));

try {
    // Touching the container can fail (for example when the database is
    // unreachable), so it happens inside the handler that turns any problem
    // into a clean response rather than a stack trace.
    $app->images()->setBaseUrl($request->basePath);

    $isApi = str_starts_with($request->path, '/api/');

    if ($isApi) {
        // The public read API and the login form are reachable without a session;
        // every other API route validates the CSRF token first.
        if (! str_starts_with($request->path, '/api/public/')) {
            $app->csrf()->check($request);
        }

        $matched = $router->match($request->method, $request->path);

        if ($matched === null) {
            throw new HttpException('Not found.', 404);
        }

        [$handler, $params] = $matched;
        $handler($request, $params);

        exit;
    }

    // Anything else is the SPA shell.
    $csrfToken = $app->csrf()->token();
    $basePath = $request->basePath;

    require dirname(__DIR__).'/src/View/shell.php';
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->status(), $e->errors());
} catch (\Throwable $e) {  // includes PDO failures and any programming error
    error_log(sprintf('[testimonials] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    // Never leak an internal message or a stack trace to the client.
    Response::error(
        $config['app']['debug'] ? $e->getMessage() : 'Something went wrong. Please try again.',
        500
    );
}
