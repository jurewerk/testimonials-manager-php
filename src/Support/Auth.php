<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\UserRepository;

class Auth
{
    private Session $session;

    private UserRepository $users;

    private ?array $user = null;

    public function __construct(Session $session, UserRepository $users)
    {
        $this->session = $session;
        $this->users = $users;
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);

        // Always run a hash comparison so a missing account and a wrong
        // password take a comparable amount of time.
        $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

        if (! password_verify($password, $hash) || $user === null) {
            return null;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->user = $user;

        return $this->publicUser($user);
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
    }

    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->session->get('user_id');

        if (! is_int($id)) {
            return null;
        }

        return $this->user = $this->users->find($id);
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @throws HttpException
     */
    public function require(): array
    {
        $user = $this->user();

        if ($user === null) {
            throw new HttpException('Unauthenticated.', 401);
        }

        return $user;
    }

    public function publicUser(array $user): array
    {
        return ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']];
    }
}
