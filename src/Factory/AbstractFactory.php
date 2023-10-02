<?php

declare(strict_types=1);

namespace Conia\Http\Factory;

use Conia\Chuck\Exception\RuntimeException;
use Conia\Chuck\Factory;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactory;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Psr\Http\Message\StreamFactoryInterface as PsrStreamFactory;
use Psr\Http\Message\StreamInterface as PsrStream;
use Stringable;

abstract class AbstractFactory implements Factory
{
    protected PsrResponseFactory $responseFactory;
    protected PsrStreamFactory $streamFactory;

    abstract public function request(): PsrServerRequest;

    public function response(int $code = 200, string $reasonPhrase = ''): PsrResponse
    {
        return $this->responseFactory->createResponse($code, $reasonPhrase);
    }

    public function stream(mixed $content = ''): PsrStream
    {
        if (is_string($content) || $content instanceof Stringable) {
            return $this->streamFactory->createStream((string)$content);
        }

        if (is_resource($content)) {
            return $this->streamFactory->createStreamFromResource($content);
        }

        throw new RuntimeException('Only strings, Stringable or resources are allowed');
    }

    public function streamFromFile(string $filename, string $mode = 'r'): PsrStream
    {
        return $this->streamFactory->createStreamFromFile($filename, $mode);
    }
}
