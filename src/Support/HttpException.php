<?php

declare(strict_types=1);

namespace App\Support;

class HttpException extends \RuntimeException
{
    private array $errors;

    public function __construct(string $message, int $status = 400, array $errors = [])
    {
        parent::__construct($message, $status);
        $this->errors = $errors;
    }

    public function status(): int
    {
        return (int) $this->getCode();
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
