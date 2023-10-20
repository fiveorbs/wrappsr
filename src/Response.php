<?php

declare(strict_types=1);

namespace Conia\Http;

use Conia\Http\Exception\RuntimeException;
use Conia\Http\Factory;
use finfo;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\StreamInterface as PsrStream;
use Traversable;

/** @psalm-api */
class Response
{
    public function __construct(
        protected PsrResponse $psr,
        protected readonly Factory|null $factory = null,
    ) {
    }

    public static function fromFactory(Factory $factory): self
    {
        return new self($factory->response(), $factory);
    }

    public function psr(): PsrResponse
    {
        return $this->psr;
    }

    public function setPsr(PsrResponse $psr): static
    {
        $this->psr = $psr;

        return $this;
    }

    public function status(int $statusCode, ?string $reasonPhrase = null): static
    {
        if (empty($reasonPhrase)) {
            $this->psr = $this->psr->withStatus($statusCode);
        } else {
            $this->psr = $this->psr->withStatus($statusCode, $reasonPhrase);
        }

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->psr->getStatusCode();
    }

    public function getReasonPhrase(): string
    {
        return $this->psr->getReasonPhrase();
    }

    public function protocolVersion(string $protocol): static
    {
        $this->psr = $this->psr->withProtocolVersion($protocol);

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->psr = $this->psr->withAddedHeader($name, $value);

        return $this;
    }

    public function removeHeader(string $name): static
    {
        $this->psr = $this->psr->withoutHeader($name);

        return $this;
    }

    public function headers(): array
    {
        return $this->psr->getHeaders();
    }

    public function getHeader(string $name): array
    {
        return $this->psr->getHeader($name);
    }

    public function hasHeader(string $name): bool
    {
        return $this->psr->hasHeader($name);
    }

    public function body(PsrStream|string $body): static
    {
        if ($body instanceof PsrStream) {
            $this->psr = $this->psr->withBody($body);

            return $this;
        }

        if ($this->factory) {
            $this->psr = $this->psr->withBody($this->factory->stream($body));

            return $this;
        }

        throw new RuntimeException('No factory instance set in response object');
    }

    public function getBody(): PsrStream
    {
        return $this->psr->getBody();
    }

    public function write(string $content): static
    {
        $this->psr->getBody()->write($content);

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
        $this->psr = $this->psr
            ->withStatus($code, $reasonPhrase)
            ->withAddedHeader('Content-Type', $contentType);

        if ($body) {
            assert(isset($this->factory));
            $this->psr = $this->psr->withBody($this->factory->stream($body));
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
        bool $throwNotFound = true,
        int $code = 200,
        string $reasonPhrase = '',
    ): static {
        $this->validateFile($file, $throwNotFound);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $contentType = $finfo->file($file);
        $finfo = new finfo(FILEINFO_MIME_ENCODING);
        $encoding = $finfo->file($file);
        assert(isset($this->factory));
        $stream = $this->factory->streamFromFile($file, 'rb');

        $this->psr = $this->psr
            ->withStatus($code, $reasonPhrase)
            ->withAddedHeader('Content-Type', $contentType)
            ->withAddedHeader('Content-Transfer-Encoding', $encoding)
            ->withBody($stream);

        $size = $stream->getSize();

        if (!is_null($size)) {
            $this->psr = $this->psr->withAddedHeader('Content-Length', (string)$size);
        }

        return $this;
    }

    public function download(
        string $file,
        string $newName = '',
        bool $throwNotFound = true,
        int $code = 200,
        string $reasonPhrase = '',
    ): static {
        $response = $this->file($file, $throwNotFound, $code, $reasonPhrase);
        $response->header(
            'Content-Disposition',
            'attachment; filename="' . ($newName ?: basename($file)) . '"'
        );

        return $response;
    }

    public function sendfile(
        string $file,
        bool $throwNotFound = true,
        int $code = 200,
        string $reasonPhrase = '',
    ): static {
        $this->validateFile($file, $throwNotFound);
        $server = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
        $this->psr = $this->psr->withStatus($code, $reasonPhrase);

        if (strpos($server, 'nginx') !== false) {
            $this->psr = $this->psr->withAddedHeader('X-Accel-Redirect', $file);
        } else {
            $this->psr = $this->psr->withAddedHeader('X-Sendfile', $file);
        }

        return $this;
    }

    protected function validateFile(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('File not found');
        }
    }
}
