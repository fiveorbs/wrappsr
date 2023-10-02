<?php

declare(strict_types=1);

namespace Conia\Http\Exception;

use Throwable;

/** @psalm-api */
class HttpMethodNotAllowed extends HttpError
{
    public function __construct(
        string $message = 'Method Not Allowed',
        int $code = 405,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
