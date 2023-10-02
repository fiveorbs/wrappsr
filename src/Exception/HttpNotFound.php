<?php

declare(strict_types=1);

namespace Conia\Http\Exception;

use Throwable;

class HttpNotFound extends HttpError
{
    public function __construct(
        string $message = 'Not Found',
        int $code = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
