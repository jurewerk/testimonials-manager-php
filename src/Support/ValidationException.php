<?php

declare(strict_types=1);

namespace App\Support;

class ValidationException extends HttpException
{
    public function __construct(array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct($message, 422, $errors);
    }
}
