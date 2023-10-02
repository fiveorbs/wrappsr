<?php

declare(strict_types=1);

namespace Conia\Http;

use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Psr\Http\Message\StreamInterface as PsrStream;

/** @psalm-api */
interface Factory
{
    public function request(): PsrServerRequest;

    public function response(int $code = 200, string $reasonPhrase = ''): PsrResponse;

    public function stream(mixed $content = ''): PsrStream;

    public function streamFromFile(string $filename, string $mode = 'r'): PsrStream;
}
