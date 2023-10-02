<?php

declare(strict_types=1);

namespace Conia\Http\Factory;

use Conia\Chuck\Exception\RuntimeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Throwable;

/** @psalm-api */
class Nyholm extends AbstractFactory
{
    protected Psr17Factory $factory;

    public function __construct()
    {
        try {
            $this->factory = $this->streamFactory = $this->responseFactory = new Psr17Factory();
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            throw new RuntimeException('Install laminas/laminas-diactoros');
            // @codeCoverageIgnoreEnd
        }
    }

    public function request(): PsrServerRequest
    {
        $creator = new ServerRequestCreator(
            $this->factory, // ServerRequestFactory
            $this->factory, // UriFactory
            $this->factory, // UploadedFileFactory
            $this->factory  // StreamFactory
        );

        return $creator->fromGlobals();
    }
}
