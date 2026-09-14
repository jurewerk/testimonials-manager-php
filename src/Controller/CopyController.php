<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;

class CopyController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /** Copy testimonials from another country of the same product. */
    public function copy(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $destination = $this->landing((int) $params['id']);

        $data = Validator::make($request->all(), [
            'source_landing_id' => 'required|integer',
            'strategy' => 'required|in:replace,append,empty,skip',
            'version' => 'required|integer',
            'ids' => 'nullable|array|max:1000',
        ])->validate();

        $source = $this->landing($data['source_landing_id']);

        $result = $this->app->copies()->copy(
            $destination,
            $source,
            $data['strategy'],
            $data['version'],
            (int) $user['id'],
            null,
            false,
            $data['ids'] ?? null
        );

        Response::json(['data' => $result]);
    }

    /**
     * Mock translation. A preview returns the proposed rows without writing
     * anything; only a confirmed request persists them.
     */
    public function translate(Request $request, array $params): void
    {
        $user = $this->app->auth()->require();
        $destination = $this->landing((int) $params['id']);

        $data = Validator::make($request->all(), [
            'provider' => 'required|in:openai,claude,gemini',
            'strategy' => 'required|in:replace,append,empty,skip',
            'generate_names' => 'required|boolean',
            'preview' => 'required|boolean',
            'version' => 'required|integer',
            'ids' => 'nullable|array|max:1000',
            'source_landing_id' => 'nullable|integer',
        ])->validate();

        $source = isset($data['source_landing_id'])
            ? $this->landing($data['source_landing_id'])
            : $this->app->landings()->masterFor($destination['product_id']);

        if ($source === null) {
            throw new HttpException('This product has no English master landing to translate from.', 422);
        }

        $provider = $this->app->ai()->make($data['provider']);

        if ($data['preview']) {
            $rows = $this->app->testimonials()->allForLanding($source['id']);

            if (! empty($data['ids'])) {
                $wanted = array_flip(array_map('intval', $data['ids']));
                $rows = array_values(array_filter($rows, static fn ($r) => isset($wanted[(int) $r['id']])));
            }

            $preview = array_map(static fn (array $row) => [
                'id' => (int) $row['id'],
                'author_name' => $row['author_name'],
                'comment' => $row['comment'],
                'translated_author_name' => $data['generate_names']
                    ? $provider->generateAuthorName($destination['country_code'], $row['gender'])
                    : $row['author_name'],
                'translated_comment' => $provider->translate($row['comment'], $destination['country_code']),
            ], $rows);

            Response::json(['data' => ['preview' => $preview, 'provider' => $provider->label()]]);

            return;
        }

        $result = $this->app->copies()->copy(
            $destination,
            $source,
            $data['strategy'],
            $data['version'],
            (int) $user['id'],
            $provider,
            $data['generate_names'],
            $data['ids'] ?? null
        );

        Response::json(['data' => $result]);
    }

    public function providers(): void
    {
        $this->app->auth()->require();

        Response::json(['data' => $this->app->ai()->list()]);
    }

    private function landing(int $id): array
    {
        $landing = $this->app->landings()->find($id);

        if ($landing === null) {
            throw new HttpException('Landing not found.', 404);
        }

        return $landing;
    }
}
