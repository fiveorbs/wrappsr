<?php

declare(strict_types=1);

namespace Conia\Http;

use Psr\Http\Message\StreamInterface as PsrStream;

trait WrapsMessage
{
    public function getProtocolVersion(): string
    {
        return $this->psr->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $this->psr = $this->psr->withProtocolVersion($version);

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->psr->getHeaders();
    }

    public function hasHeader(string $header): bool
    {
        return $this->psr->hasHeader($header);
    }

    public function getHeader(string $header): array
    {
        return $this->psr->getHeader($header);
    }

    public function getHeaderLine(string $header): string
    {
        return $this->psr->getHeaderLine($header);
    }

    public function withHeader(string $header, string $value): static
    {
        $this->psr = $this->psr->withHeader($header, $value);

        return $this;
    }

    public function withAddedHeader(string $header, string $value): static
    {
        $this->psr = $this->psr->withAddedHeader($header, $value);

        return $this;
    }

    public function withoutHeader(string $header): static
    {
        $this->psr = $this->psr->withoutHeader($header);

        return $this;
    }

    public function getBody(): PsrStream
    {
        return $this->psr->getBody();
    }

    public function withBody(PsrStream $body): static
    {
        $this->psr = $this->psr->withBody($body);

        return $this;
    }
}
