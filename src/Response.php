<?php

declare(strict_types=1);

namespace Conia\Http;

use Conia\Http\Exception\FileNotFoundException;
use Conia\Http\Exception\RuntimeException;
use finfo;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactory;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\StreamFactoryInterface as PsrStreamFactory;
use Psr\Http\Message\StreamInterface as PsrStream;
use Stringable;
use Traversable;

/** @psalm-api */
class Response
{
    public function __construct(
        protected PsrResponse $psrResponse,
        protected readonly PsrStreamFactory|null $streamFactory = null,
    ) {
    }

    public static function fromFactory(PsrResponseFactory $responseFactory, PsrStreamFactory $streamFactory): self
    {
        return new self($responseFactory->createResponse(), $streamFactory);
    }

    public function unwrap(): PsrResponse
    {
        return $this->psrResponse;
    }

    public function wrap(PsrResponse $response): static
    {
        $this->psrResponse = $response;

        return $this;
    }

    public function status(int $statusCode, ?string $reasonPhrase = null): static
    {
        if (empty($reasonPhrase)) {
            $this->psrResponse = $this->psrResponse->withStatus($statusCode);
        } else {
            $this->psrResponse = $this->psrResponse->withStatus($statusCode, $reasonPhrase);
        }

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->psrResponse->getStatusCode();
    }

    public function getReasonPhrase(): string
    {
        return $this->psrResponse->getReasonPhrase();
    }

    public function getProtocolVersion(): string
    {
        return $this->psrResponse->getProtocolVersion();
    }

    public function protocolVersion(string $protocol): static
    {
        $this->psrResponse = $this->psrResponse->withProtocolVersion($protocol);

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->psrResponse = $this->psrResponse->withAddedHeader($name, $value);

        return $this;
    }

    public function removeHeader(string $name): static
    {
        $this->psrResponse = $this->psrResponse->withoutHeader($name);

        return $this;
    }

    public function headers(): array
    {
        return $this->psrResponse->getHeaders();
    }

    public function getHeader(string $name): array
    {
        return $this->psrResponse->getHeader($name);
    }

    public function hasHeader(string $name): bool
    {
        return $this->psrResponse->hasHeader($name);
    }

    public function body(PsrStream|string $body): static
    {
        if ($body instanceof PsrStream) {
            $this->psrResponse = $this->psrResponse->withBody($body);

            return $this;
        }

        if ($this->streamFactory) {
            $this->psrResponse = $this->psrResponse->withBody($this->streamFactory->createStream($body));

            return $this;
        }

        throw new RuntimeException('No factory instance set in response object');
    }

    public function getBody(): PsrStream
    {
        return $this->psrResponse->getBody();
    }

    public function write(string $content): static
    {
        $this->psrResponse->getBody()->write($content);

        return $this;
    }

    public function redirect(string $url, int $code = 302): static
    {
        $this->header('Location', $url);
        $this->status($code);

        return $this;
    }

    /**
     * @param null|PsrStream|resource|string $body
     */
    public function withContentType(
        string $contentType,
        mixed $body = null,
        int $code = 200,
        string $reasonPhrase = ''
    ): static {
        $this->psrResponse = $this->psrResponse
            ->withStatus($code, $reasonPhrase)
            ->withAddedHeader('Content-Type', $contentType);

        if ($body) {
            assert(isset($this->streamFactory));

            if ($body instanceof PsrStream) {
                $stream = $body;
            } elseif (is_string($body) || $body instanceof Stringable) {
                $stream = $this->streamFactory->createStream((string)$body);
            } elseif (is_resource($body)) {
                $stream = $this->streamFactory->createStreamFromResource($body);
            } else {
                throw new RuntimeException('Only strings, Stringable or resources are allowed');
            }

            $this->psrResponse = $this->psrResponse->withBody($stream);
        }

        return $this;
    }

    /**
     * @param null|PsrStream|resource|string $body
     */
    public function html(mixed $body = null, int $code = 200, string $reasonPhrase = ''): static
    {
        return $this->withContentType('text/html', $body, $code, $reasonPhrase);
    }

    /**
     * @param null|PsrStream|resource|string $body
     */
    public function text(mixed $body = null, int $code = 200, string $reasonPhrase = ''): static
    {
        return $this->withContentType('text/plain', $body, $code, $reasonPhrase);
    }

    public function json(
        mixed $data,
        int $code = 200,
        string $reasonPhrase = '',
        int $flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ): static {
        if ($data instanceof Traversable) {
            $body = json_encode(iterator_to_array($data), $flags);
        } else {
            $body = json_encode($data, $flags);
        }

        return $this->withContentType('application/json', $body, $code, $reasonPhrase);
    }

    public function file(
        string $file,
        int $code = 200,
        string $reasonPhrase = '',
    ): static {
        $this->validateFile($file);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $contentType = $finfo->file($file);
        $finfo = new finfo(FILEINFO_MIME_ENCODING);
        $encoding = $finfo->file($file);
        assert(isset($this->streamFactory));
        $stream = $this->streamFactory->createStreamFromFile($file, 'rb');

        $this->psrResponse = $this->psrResponse
            ->withStatus($code, $reasonPhrase)
            ->withAddedHeader('Content-Type', $contentType)
            ->withAddedHeader('Content-Transfer-Encoding', $encoding)
            ->withBody($stream);

        $size = $stream->getSize();

        if (!is_null($size)) {
            $this->psrResponse = $this->psrResponse->withAddedHeader('Content-Length', (string)$size);
        }

        return $this;
    }

    public function download(
        string $file,
        string $newName = '',
        int $code = 200,
        string $reasonPhrase = '',
    ): static {
        $response = $this->file($file, $code, $reasonPhrase);
        $response->header(
            'Content-Disposition',
            'attachment; filename="' . ($newName ?: basename($file)) . '"'
        );

        return $response;
    }

    public function sendfile(
        string $file,
        int $code = 200,
        string $reasonPhrase = '',
    ): static {
        $this->validateFile($file);
        $server = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
        $this->psrResponse = $this->psrResponse->withStatus($code, $reasonPhrase);

        if (strpos($server, 'nginx') !== false) {
            $this->psrResponse = $this->psrResponse->withAddedHeader('X-Accel-Redirect', $file);
        } else {
            $this->psrResponse = $this->psrResponse->withAddedHeader('X-Sendfile', $file);
        }

        return $this;
    }

    protected function validateFile(string $file): void
    {
        if (!is_file($file)) {
            throw new FileNotFoundException('File not found: ' . $file);
        }
    }
}
