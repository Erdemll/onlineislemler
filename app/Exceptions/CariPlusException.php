<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class CariPlusException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** @return array<string, int|string|null> */
    public function context(): array
    {
        return [
            'cari_plus_error_code' => $this->errorCode,
            'cari_plus_status' => $this->status,
        ];
    }
}
