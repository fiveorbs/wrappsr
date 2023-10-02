<?php

declare(strict_types=1);

namespace Conia\Http\Exception;

use Throwable;

/** @psalm-api */
class HttpUnauthorized extends HttpError
{
    public function __construct(string $message = 'Unauthorized', int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
