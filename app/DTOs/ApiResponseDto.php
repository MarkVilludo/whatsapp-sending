<?php

namespace App\DTOs;

class ApiResponseDto
{
    /**
     * @param array<string, mixed>|string|null $error
     */
    public function __construct(
        private readonly string $message,
        private readonly mixed $data = null,
        private readonly mixed $error = null
    ) {
    }

    /**
     * @param mixed $data
     */
    public static function success(string $message, mixed $data = null): self
    {
        return new self($message, $data);
    }

    /**
     * @param array<string, mixed>|string|null $error
     */
    public static function error(string $message, mixed $error = null): self
    {
        return new self($message, null, $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['message' => $this->message];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        if ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
