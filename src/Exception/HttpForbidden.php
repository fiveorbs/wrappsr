<?php

declare(strict_types=1);

namespace Conia\Http\Exception;

use Throwable;

/** @psalm-api */
class HttpForbidden extends HttpError
{
    public function __construct(
        string $message = 'Forbidden',
        int $code = 403,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
