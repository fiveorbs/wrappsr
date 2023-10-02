<?php

declare(strict_types=1);

namespace Conia\Http\Factory;

use Conia\Chuck\Exception\RuntimeException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Throwable;

/** @psalm-api */
class Guzzle extends AbstractFactory
{
    public function __construct()
    {
        try {
            $this->streamFactory = $this->responseFactory = new HttpFactory();
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            throw new RuntimeException('Install guzzlehttp/psr7');
            // @codeCoverageIgnoreEnd
        }
    }

    public function request(): PsrServerRequest
    {
        return ServerRequest::fromGlobals();
    }
}
