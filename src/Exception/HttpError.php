<?php

declare(strict_types=1);

namespace Conia\Http\Exception;

use Throwable;

/**
 * @psalm-api
 *
 * @psalm-consistent-constructor
 */
abstract class HttpError extends RuntimeException
{
    protected mixed $payload = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function withPayload(mixed $payload): static
    {
        $exception = new static();
        $exception->setPayload($payload);

        return $exception;
    }

    public function getTitle(): string
    {
        return (string)$this->getCode() . ' ' . $this->getMessage();
    }

    public function setPayload(mixed $payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }
}
