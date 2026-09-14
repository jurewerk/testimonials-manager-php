<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;

class ImageController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function store(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $id = (int) $params['id'];
        $testimonial = $this->app->testimonials()->findRaw($id);

        if ($testimonial === null) {
            throw new HttpException('Testimonial not found.', 404);
        }

        $uploads = $request->files('images');

        if ($uploads === []) {
            throw new \App\Support\ValidationException(['images' => ['Choose at least one image.']]);
        }

        if (count($uploads) > $this->app->config['uploads']['max_per_request']) {
            throw new \App\Support\ValidationException(['images' => ['Too many images in one request.']]);
        }

        $this->app->db()->transaction(function () use ($id, $uploads, $user) {
            $start = $this->app->images()->nextSortOrder($id);
            $created = $this->app->imageService()->store($id, $uploads, $start);
            $this->app->activity()->record((int) $user['id'], 'images added', 'testimonial', $id, null, ['count' => count($created)]);
        });

        Response::json(['data' => $this->app->testimonials()->find($id)], 201);
    }

    public function reorder(Request $request, array $params): void
    {
        $this->app->auth()->require();
        $id = (int) $params['id'];
        $testimonial = $this->app->testimonials()->findRaw($id);

        if ($testimonial === null) {
            throw new HttpException('Testimonial not found.', 404);
        }

        $data = Validator::make($request->all(), [
            'ids' => 'required|array|max:100',
            'version' => 'required|integer',
        ])->validate();

        if ($data['version'] !== (int) $testimonial['version']) {
            throw new HttpException('This testimonial changed in another session. Reload and try again.', 409);
        }

        $existing = array_map('intval', array_column($this->app->images()->forTestimonial($id), 'id'));
        $ids = array_map('intval', $data['ids']);
        $sorted = $ids;
        sort($sorted);
        sort($existing);

        if ($sorted !== $existing) {
            throw new \App\Support\ValidationException(['ids' => ['The order must list every image of this testimonial exactly once.']]);
        }

        $this->app->images()->applyOrder($ids);

        Response::json(['data' => $this->app->testimonials()->find($id)]);
    }

    public function destroy(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $image = $this->app->images()->find((int) $params['id']);

        if ($image === null) {
            throw new HttpException('Image not found.', 404);
        }

        $this->app->db()->transaction(function () use ($image, $user) {
            $this->app->images()->delete((int) $image['id']);
            $this->app->imageService()->deleteFile($image['path']);
            $this->app->imageService()->deleteFile($image['thumbnail_path']);
            $this->app->activity()->record((int) $user['id'], 'image removed', 'testimonial', (int) $image['testimonial_id'], ['file' => $image['original_filename']], null);
        });

        Response::noContent();
    }

    /**
     * Streams a stored image. Files live outside the document root, so the only
     * way to reach one is through this whitelist-by-database lookup.
     */
    public function serve(Request $request, array $params): void
    {
        $name = basename((string) $params['path']);
        $row = $this->app->db()->selectOne(
            'SELECT mime_type FROM testimonial_images WHERE path = ? OR thumbnail_path = ? LIMIT 1',
            [$name, $name]
        );

        $file = $this->app->imageService()->absolutePath($name);

        if ($row === null || ! is_file($file)) {
            throw new HttpException('Image not found.', 404);
        }

        header('Content-Type: '.$row['mime_type']);
        header('Content-Length: '.filesize($file));
        // The file name is a UUID and its content never changes, so it can be
        // cached hard. No session is started on this route, which is what keeps
        // PHP from stamping the response no-cache.
        header('Cache-Control: public, max-age=86400, immutable');
        header('X-Content-Type-Options: nosniff');

        if ($request->method === 'HEAD') {
            return;
        }

        readfile($file);
    }
}
