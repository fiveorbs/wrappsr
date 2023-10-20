<?php

declare(strict_types=1);

namespace Conia\Http;

use Psr\Http\Server\MiddlewareInterface;

abstract class Middleware implements MiddlewareInterface
{
    /** @param callable(Request): ResponseWrapper $next */
    abstract public function __invoke(
        Request $request,
        callable $next
    ): Response;
}
