<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;

class AuthController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function login(Request $request): void
    {
        $data = Validator::make($request->all(), [
            'email' => 'required|string|max:255',
            'password' => 'required|string|max:1000',
        ])->validate();

        $user = $this->app->auth()->attempt($data['email'], $data['password']);

        if ($user === null) {
            throw new HttpException('Those credentials do not match our records.', 401);
        }

        Response::json(['data' => $user]);
    }

    public function logout(): void
    {
        $this->app->auth()->logout();
        Response::noContent();
    }

    public function me(): void
    {
        $user = $this->app->auth()->user();

        if ($user === null) {
            throw new HttpException('Unauthenticated.', 401);
        }

        Response::json(['data' => $this->app->auth()->publicUser($user)]);
    }
}
