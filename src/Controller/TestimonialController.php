<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\Database;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;

class TestimonialController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    private const RULES = [
        'author_name' => 'required|string|max:120',
        'comment' => 'required|string|max:2000',
        'link' => 'nullable|string|url|max:2048',
        'rating_mode' => 'required|in:fixed,random',
        'rating' => 'nullable|integer|between:1,5',
        'gender' => 'required|in:male,female,unisex',
        'is_active' => 'required|boolean',
    ];

    /** A landing's testimonials, or the inherited English set. */
    public function index(Request $request, array $params): void
    {
        $this->app->auth()->require();

        $landing = $this->landing((int) $params['id']);
        $resolved = $this->app->fallback()->resolve($landing);
        $source = $resolved['source'];

        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        // Inherited rows are the master's ACTIVE set; local lists show everything.
        $page = $this->app->testimonials()->paginateForLanding(
            $source['id'],
            $perPage,
            max(1, (int) $request->query('page', 1)),
            $resolved['inherited']
        );

        $page['inheritance'] = $this->app->fallback()->metadata($landing);
        $page['landing'] = $landing;

        Response::json($page);
    }

    public function store(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $landing = $this->landing((int) $params['id']);

        $data = $this->validatePayload($request->all());

        $id = $this->app->db()->transaction(function () use ($landing, $data, $user) {
            $id = $this->app->testimonials()->create($data + [
                'landing_id' => $landing['id'],
                'sort_order' => $this->app->testimonials()->nextSortOrder($landing['id']),
                'created_by' => (int) $user['id'],
            ]);

            $this->app->activity()->record((int) $user['id'], 'created', 'testimonial', $id, null, $data);
            $this->app->landings()->bumpVersion($landing['id']);

            return $id;
        });

        Response::json(['data' => $this->app->testimonials()->find($id)], 201);
    }

    public function show(Request $request, array $params): void
    {
        $this->app->auth()->require();

        Response::json(['data' => $this->testimonial((int) $params['id'])]);
    }

    public function update(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $id = (int) $params['id'];
        $existing = $this->app->testimonials()->findRaw($id);

        if ($existing === null) {
            throw new HttpException('Testimonial not found.', 404);
        }

        $data = $this->validatePayload($request->all());
        $version = $request->input('version');

        if ($version !== null && (int) $version !== (int) $existing['version']) {
            throw new HttpException('This testimonial changed in another session. Reload and try again.', 409);
        }

        $this->app->db()->transaction(function () use ($id, $data, $existing, $user) {
            $this->app->testimonials()->update($id, $data, (int) $user['id']);
            $this->app->activity()->record((int) $user['id'], 'updated', 'testimonial', $id, $this->auditable($existing), $data);
        });

        Response::json(['data' => $this->app->testimonials()->find($id)]);
    }

    public function destroy(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $id = (int) $params['id'];
        $existing = $this->app->testimonials()->findRaw($id);

        if ($existing === null) {
            throw new HttpException('Testimonial not found.', 404);
        }

        $this->app->db()->transaction(function () use ($id, $existing, $user) {
            foreach ($this->app->images()->forTestimonial($id) as $image) {
                $this->app->imageService()->deleteFile($image['path']);
                $this->app->imageService()->deleteFile($image['thumbnail_path']);
            }

            $this->app->testimonials()->delete($id);
            $this->app->activity()->record((int) $user['id'], 'deleted', 'testimonial', $id, $this->auditable($existing), null);
            $this->app->landings()->bumpVersion((int) $existing['landing_id']);
        });

        Response::noContent();
    }

    /** Activate, deactivate or delete a selection in one transaction. */
    public function bulk(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $landing = $this->landing((int) $params['id']);

        $data = Validator::make($request->all(), [
            'ids' => 'required|array|min:1|max:1000',
            'action' => 'required|in:activate,deactivate,delete',
            'version' => 'required|integer',
        ])->validate();

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        $version = $this->app->db()->transaction(function () use ($landing, $ids, $data, $user) {
            $current = $this->app->landings()->lockForUpdate($landing['id']);

            if ((int) $current['version'] !== $data['version']) {
                throw new HttpException('This landing changed in another session. Reload and try again.', 409);
            }

            // Only rows that actually belong to this landing may be touched.
            $in = Database::placeholders($ids);
            $owned = array_map('intval', array_column(
                $this->app->db()->select("SELECT id FROM testimonials WHERE landing_id = ? AND id IN ($in)", array_merge([$landing['id']], $ids)),
                'id'
            ));

            if (count($owned) !== count($ids)) {
                throw new HttpException('Some testimonials do not belong to this landing.', 403);
            }

            if ($data['action'] === 'delete') {
                foreach ($owned as $id) {
                    foreach ($this->app->images()->forTestimonial($id) as $image) {
                        $this->app->imageService()->deleteFile($image['path']);
                        $this->app->imageService()->deleteFile($image['thumbnail_path']);
                    }

                    $this->app->activity()->record((int) $user['id'], 'deleted', 'testimonial', $id, null, null);
                }

                $this->app->testimonials()->deleteMany($owned);
                $this->app->testimonials()->applyOrder($this->app->testimonials()->idsForLanding($landing['id']));
            } else {
                $this->app->testimonials()->setActive($owned, $data['action'] === 'activate', (int) $user['id']);

                foreach ($owned as $id) {
                    $this->app->activity()->record((int) $user['id'], $data['action'].'d', 'testimonial', $id, null, null);
                }
            }

            return $this->app->landings()->bumpVersion($landing['id']);
        });

        Response::json(['data' => ['affected' => count($ids), 'version' => $version]]);
    }

    public function reorder(Request $request, array $params): void
    {
        $this->app->auth()->require();
        $landing = $this->landing((int) $params['id']);

        $data = Validator::make($request->all(), [
            'ids' => 'required|array|max:1000',
            'version' => 'required|integer',
        ])->validate();

        $version = $this->app->ordering()->reorder($landing, $data['ids'], $data['version']);

        Response::json(['data' => ['version' => $version]]);
    }

    public function activity(Request $request, array $params): void
    {
        $this->app->auth()->require();

        Response::json(['data' => $this->app->activity()->forEntity('testimonial', (int) $params['id'])]);
    }

    /** Generates a country-plausible author name through a mock provider. */
    public function generateName(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $id = (int) $params['id'];
        $existing = $this->app->testimonials()->findRaw($id);

        if ($existing === null) {
            throw new HttpException('Testimonial not found.', 404);
        }

        $data = Validator::make($request->all(), [
            'provider' => 'required|in:openai,claude,gemini',
            'version' => 'required|integer',
        ])->validate();

        if ($data['version'] !== (int) $existing['version']) {
            throw new HttpException('This testimonial changed in another session. Reload and try again.', 409);
        }

        $landing = $this->app->landings()->find((int) $existing['landing_id']);
        $name = $this->app->ai()->make($data['provider'])->generateAuthorName($landing['country_code'], $existing['gender']);
        $name = mb_substr($name, 0, 120);

        $payload = [
            'author_name' => $name,
            'comment' => $existing['comment'],
            'link' => $existing['link'],
            'rating_mode' => $existing['rating_mode'],
            'rating' => $existing['rating'] === null ? null : (int) $existing['rating'],
            'gender' => $existing['gender'],
            'is_active' => (bool) $existing['is_active'],
        ];

        $this->app->db()->transaction(function () use ($id, $payload, $existing, $user) {
            $this->app->testimonials()->update($id, $payload, (int) $user['id']);
            $this->app->activity()->record((int) $user['id'], 'name generated', 'testimonial', $id, $this->auditable($existing), ['author_name' => $payload['author_name']]);
        });

        Response::json(['data' => $this->app->testimonials()->find($id)]);
    }

    private function validatePayload(array $input): array
    {
        $data = Validator::make($input, self::RULES)->validate();

        // A random rating is decided at display time, so storing one would be a lie.
        if ($data['rating_mode'] === 'random') {
            $data['rating'] = null;
        } elseif ($data['rating'] === null) {
            throw new \App\Support\ValidationException(['rating' => ['A fixed rating requires a value between 1 and 5.']]);
        }

        return $data;
    }

    private function auditable(array $row): array
    {
        return array_intersect_key($row, array_flip(['author_name', 'comment', 'link', 'rating_mode', 'rating', 'gender', 'is_active', 'sort_order']));
    }

    private function landing(int $id): array
    {
        $landing = $this->app->landings()->find($id);

        if ($landing === null) {
            throw new HttpException('Landing not found.', 404);
        }

        return $landing;
    }

    private function testimonial(int $id): array
    {
        $testimonial = $this->app->testimonials()->find($id);

        if ($testimonial === null) {
            throw new HttpException('Testimonial not found.', 404);
        }

        return $testimonial;
    }
}
