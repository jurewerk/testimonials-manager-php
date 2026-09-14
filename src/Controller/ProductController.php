<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;

class ProductController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function index(Request $request): void
    {
        $this->app->auth()->require();

        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $result = $this->app->products()->paginate(
            trim((string) $request->query('search', '')),
            (string) $request->query('sort', 'parent_sku'),
            (string) $request->query('direction', 'asc'),
            $perPage,
            max(1, (int) $request->query('page', 1))
        );

        Response::json($result);
    }

    public function show(Request $request, array $params): void
    {
        $this->app->auth()->require();

        $product = $this->app->products()->find((int) $params['id']);

        if ($product === null) {
            throw new HttpException('Product not found.', 404);
        }

        Response::json(['data' => $product]);
    }

    /** Country overview: every landing of the product with its counters. */
    public function landings(Request $request, array $params): void
    {
        $this->app->auth()->require();

        $product = $this->app->products()->find((int) $params['id']);

        if ($product === null) {
            throw new HttpException('Product not found.', 404);
        }

        Response::json(['data' => $this->app->landings()->forProductWithCounts($product['id'])]);
    }
}
