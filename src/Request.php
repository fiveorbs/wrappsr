<?php

declare(strict_types=1);

namespace Conia\Http;

use Conia\Http\Exception\OutOfBoundsException;
use Conia\Http\Exception\RuntimeException;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Psr\Http\Message\StreamInterface as PsrStream;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;
use Psr\Http\Message\UriInterface as PsrUri;
use Throwable;

/** @psalm-api */
class Request
{
    use WrapsMessage;
    use WrapsRequest;

    public function __construct(protected PsrServerRequest $psr)
    {
    }

    public function psr(): PsrServerRequest
    {
        return $this->psr;
    }

    public function setPsr(PsrServerRequest $psr): static
    {
        $this->psr = $psr;

        return $this;
    }

    public function params(): array
    {
        return $this->psr->getQueryParams();
    }

    public function param(string $key, mixed $default = null): mixed
    {
        $params = $this->psr->getQueryParams();
        $error = 'Query string variable not found';

        return $this->returnOrFail($params, $key, $default, $error, func_num_args());
    }

    public function form(): ?array
    {
        $body = $this->psr->getParsedBody();
        assert(is_null($body) || is_array($body));

        return $body;
    }

    public function field(string $key, mixed $default = null): mixed
    {
        $body = $this->psr->getParsedBody();
        assert(is_null($body) || is_array($body));
        $error = 'Form field not found';

        return $this->returnOrFail($body, $key, $default, $error, func_num_args());
    }

    public function cookies(): array
    {
        return $this->psr->getCookieParams();
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        $params = $this->psr->getCookieParams();
        $error = 'Cookie not found';

        return $this->returnOrFail($params, $key, $default, $error, func_num_args());
    }

    public function serverParams(): array
    {
        return $this->psr->getServerParams();
    }

    public function server(string $key, mixed $default = null): mixed
    {
        $params = $this->psr->getServerParams();
        $error = 'Server parameter not found';

        return $this->returnOrFail($params, $key, $default, $error, func_num_args());
    }

    public function header(string $name): string
    {
        return $this->psr->getHeaderLine($name);
    }

    public function headers(bool $firstOnly = false): array
    {
        $headers = $this->psr->getHeaders();

        if ($firstOnly) {
            return array_combine(
                array_keys($headers),
                array_map(fn (array $v): string => $v[0], $headers),
            );
        }

        return $headers;
    }

    public function accept(): array
    {
        return explode(',', $this->getHeaderLine('Accept'));
    }

    public function attributes(): array
    {
        return $this->psr->getAttributes();
    }

    public function set(string $attribute, mixed $value): static
    {
        $this->psr = $this->psr->withAttribute($attribute, $value);

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $params = $this->psr->getAttributes();
        $error = 'Request attribute not found';

        return $this->returnOrFail($params, $key, $default, $error, func_num_args());
    }

    public function uri(): PsrUri
    {
        return $this->psr->getUri();
    }

    public function origin(): string
    {
        $uri = $this->psr->getUri();
        $scheme = $uri->getScheme();
        $origin = $scheme ? $scheme . ':' : '';
        $authority = $uri->getAuthority();
        $origin .= $authority ? '//' . $authority : '';

        return $origin;
    }

    public function method(): string
    {
        return strtoupper($this->psr->getMethod());
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method();
    }

    public function body(): PsrStream
    {
        return $this->psr->getBody();
    }

    public function json(
        int $flags = JSON_OBJECT_AS_ARRAY,
    ): mixed {
        $body = (string)$this->psr->getBody();

        return json_decode(
            $body,
            true,
            512, // PHP default value
            $flags,
        );
    }

    /**
     * Returns always a list of uploaded files, even if there is
     * only one file.
     *
     * Psalm does not support multi file uploads yet and complains
     * about type issues. We need to suppres some of these errors.
     *
     * @no-named-arguments
     *
     * @psalm-param list<string>|string ...$keys
     *
     * @throws OutOfBoundsException RuntimeException
     */
    public function files(array|string ...$keys): array
    {
        $files = $this->psr->getUploadedFiles();
        $keys = $this->validateKeys($keys);
        $result = [];

        if (count($keys) === 0) {
            return $files;
        }

        // Walk into the uploaded files structure
        foreach ($keys as $key) {
            if (array_key_exists($key, $files)) {
                // /**
                // * @psalm-suppress MixedAssignment, MixedArrayAccess
                // *
                // * Psalm does not support recursive types like:
                // *     T = array<string, string|T>
                // */
                $files = $files[$key];
            } else {
                throw new OutOfBoundsException('Invalid files key ' . $this->formatKeys($keys));
            }
        }

        // Check if it is a single file upload.
        // A multifile upload would already produce an array
        if ($files instanceof PsrUploadedFile) {
            return [$files];
        }

        assert(is_array($files));

        return $files;
    }

    /**
     * Psalm does not support multi file uploads yet and complains
     * about type issues. We need to suppres some of the errors.
     *
     * @no-named-arguments
     *
     * @psalm-param list<non-empty-string>|string ...$keys
     *
     * @throws OutOfBoundsException RuntimeException
     */
    public function file(array|string ...$keys): PsrUploadedFile
    {
        $keys = $this->validateKeys($keys);

        if (count($keys) === 0) {
            throw new RuntimeException('No file key given');
        }

        $files = $this->psr->getUploadedFiles();
        $i = 0;

        foreach ($keys as $key) {
            if (isset($files[$key])) {
                /** @var array|PsrUploadedFile */
                $files = $files[$key];
                $i++;

                if ($files instanceof PsrUploadedFile) {
                    if ($i < count($keys)) {
                        throw new OutOfBoundsException(
                            'Invalid file key (too deep) ' . $this->formatKeys($keys)
                        );
                    }

                    return $files;
                }
            } else {
                throw new OutOfBoundsException('Invalid file key ' . $this->formatKeys($keys));
            }
        }

        throw new RuntimeException('Multiple files available at key ' . $this->formatKeys($keys));
    }

    private function returnOrFail(
        array|null $array,
        string $key,
        mixed $default,
        string $error,
        int $numArgs
    ): mixed {
        if ((is_null($array) || !array_key_exists($key, $array)) && $numArgs > 1) {
            return $default;
        }

        assert(!is_null($array));

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        throw new OutOfBoundsException("{$error}: '{$key}'");
    }

    /** @psalm-param non-empty-list<string> $keys */
    private function formatKeys(array $keys): string
    {
        return implode('', array_map(
            fn ($key) => "['" . $key . "']",
            $keys
        ));
    }

    /**
     * @psalm-param list<list<string>|string> $keys
     *
     * @psalm-return list<string>
     */
    private function validateKeys(array $keys): array
    {
        if (isset($keys[0]) && is_array($keys[0])) {
            if (count($keys) > 1) {
                throw new RuntimeException('Either provide a single array or plain string arguments');
            }
            $keys = $keys[0];
        }

        /** @psalm-var list<string> */
        return $keys;
    }
}
