<?php

declare(strict_types=1);

namespace Conia\Http;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Psr\Http\Message\UriInterface as PsrUri;

trait WrapsRequest
{
    protected PsrServerRequest $psr;

    public function getServerParams(): array
    {
        return $this->psr->getServerParams();
    }

    public function withMethod(string $method): static
    {
        $this->psr = $this->psr->withMethod($method);

        return $this;
    }

    public function getMethod(): string
    {
        return $this->psr->getMethod();
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $this->psr = $this->psr->withRequestTarget($requestTarget);

        return $this;
    }

    public function getRequestTarget(): string
    {
        return $this->psr->getRequestTarget();
    }

    public function withQueryParams(array $query): static
    {
        $this->psr = $this->psr->withQueryParams($query);

        return $this;
    }

    public function getQueryParams(): array
    {
        return $this->psr->getQueryParams();
    }

    public function withParsedBody(null|array|object $data): static
    {
        $this->psr = $this->psr->withParsedBody($data);

        return $this;
    }

    public function getParsedBody(): null|array|object
    {
        return $this->psr->getParsedBody();
    }

    public function withCookieParams(array $cookies): static
    {
        $this->psr = $this->psr->withCookieParams($cookies);

        return $this;
    }

    public function getCookieParams(): array
    {
        return $this->psr->getCookieParams();
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $this->psr = $this->psr->withUploadedFiles($uploadedFiles);

        return $this;
    }

    public function getUploadedFiles(): array
    {
        return $this->psr->getUploadedFiles();
    }

    public function withUri(PsrUri $uri, bool $preserveHost = false): static
    {
        $this->psr = $this->psr->withUri($uri, $preserveHost);

        return $this;
    }

    public function getUri(): PsrUri
    {
        return $this->psr->getUri();
    }

    public function withAttribute(string $attribute, mixed $value): static
    {
        $this->psr = $this->psr->withAttribute($attribute, $value);

        return $this;
    }

    public function withoutAttribute(string $attribute): static
    {
        $this->psr = $this->psr->withoutAttribute($attribute);

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->psr->getAttributes();
    }

    public function getAttribute(string $attribute, mixed $default = null): mixed
    {
        return $this->psr->getAttribute($attribute, $default);
    }
}
