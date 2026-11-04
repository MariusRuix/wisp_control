<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class SmartOltException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public function esLimiteDePeticiones(): bool
    {
        return $this->httpStatus === 429;
    }

    /**
     * Errores que vale la pena reintentar (rate limit, caídas, timeouts).
     */
    public function esReintentable(): bool
    {
        return $this->httpStatus === 0 || $this->httpStatus === 429 || $this->httpStatus >= 500;
    }
}
