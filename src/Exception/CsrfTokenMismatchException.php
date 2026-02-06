<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Exception;

use RuntimeException;

final class CsrfTokenMismatchException extends RuntimeException
{
    public function __construct(string $message = 'CSRF token mismatch.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 419;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function headers(): array
    {
        return [];
    }
}
