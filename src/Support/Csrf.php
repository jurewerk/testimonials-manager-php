<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Double-submit CSRF protection: the token lives in the session and must be
 * echoed back in the X-CSRF-Token header on every state-changing request.
 */
class Csrf
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function token(): string
    {
        $token = $this->session->get('csrf_token');

        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('csrf_token', $token);
        }

        return $token;
    }

    public function check(Request $request): void
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $provided = $request->header('X-CSRF-Token') ?? '';

        if (! hash_equals($this->token(), $provided)) {
            throw new HttpException('CSRF token mismatch. Reload the page and try again.', 403);
        }
    }
}
