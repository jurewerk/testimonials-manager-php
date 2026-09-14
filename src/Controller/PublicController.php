<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;

/**
 * Intentionally public read API for landing-page integration.
 *
 * Returns only active, effective testimonials and no internal fields.
 */
class PublicController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function testimonials(Request $request, array $params): void
    {
        $landing = $this->app->landings()->findByExternalId((string) $params['external_id']);

        if ($landing === null) {
            throw new HttpException('Landing not found.', 404);
        }

        $resolved = $this->app->fallback()->resolve($landing);
        $source = $resolved['source'];

        $page = max(1, (int) $request->query('page', 1));
        $result = $this->app->testimonials()->paginateForLanding($source['id'], 50, $page, true);

        $images = $this->app->images()->forTestimonials(array_column($result['data'], 'id'));

        $data = array_map(
            fn (array $row) => $this->app->testimonials()->presentPublic([
                'id' => $row['id'],
                'author_name' => $row['author_name'],
                'comment' => $row['comment'],
                'link' => $row['link'],
                'rating_mode' => $row['rating_mode'],
                'rating' => $row['rating'],
            ], $images),
            $result['data']
        );

        Response::json(['data' => $data, 'meta' => $result['meta']]);
    }
}
