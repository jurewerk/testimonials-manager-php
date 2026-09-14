<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;

class SyncController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Runs the import inline and returns the summary.
     *
     * Doing it synchronously keeps the app dependency-free: there is no queue
     * worker to run on a XAMPP install, and the import of 170 landings takes
     * well under a second.
     */
    public function sync(Request $request): void
    {
        $user = $this->app->auth()->require();

        $data = Validator::make($request->all(), [
            'sku' => 'nullable|string|max:1000',
            'country' => 'nullable|string|max:8',
        ])->validate();

        $summary = $this->app->sync()->sync(array_filter([
            'sku' => $data['sku'] ?? null,
            'country' => $data['country'] ?? null,
        ]));

        $this->app->activity()->record((int) $user['id'], 'landings synchronised', 'system', 0, null, $summary);

        Response::json(['data' => $summary]);
    }
}
