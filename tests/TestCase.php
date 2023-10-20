<?php

declare(strict_types=1);

namespace Conia\Http\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Conia\Http\Request;
use PHPUnit\Framework\TestCase as BaseTestCase;
// use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use ValueError;

/**
 * @internal
 *
 * @coversNothing
 */
class TestCase extends BaseTestCase
{
    public function throws(string $exception, string $message = null): void
    {
        $this->expectException($exception);

        if ($message) {
            $this->expectExceptionMessage($message);
        }
    }

    public function request(
        array $server = [],
        array $headers = [],
        array $cookie = [],
        array $post = [],
        array $get = [],
        array $files = [],
        mixed $body = null
    ): PsrServerRequest {
        $headers = array_merge([
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'accept-encoding' => 'deflate, gzip;q=1.0, *;q=0.5',
            'accept-language' => 'en-US,en;q=0.7,de;q=0.3',
            'connection' => 'keep-alive',
            'Host' => 'www.example.com',
            'referer' => 'https://previous.example.com',
            'user-agent' => 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-GB; rv:1.9.2.6) Gecko/20100625 Firefox/3.6.6 ( .NET CLR 3.5.30729)',
        ], $headers);
        error_log(print_r($headers, true));

        $server = array_merge([
            'DOCUMENT_ROOT' => '/var/www/',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'HTTPS' => '1',
            'HTTP_ACCEPT' => $headers['accept'],
            'HTTP_ACCEPT_ENCODING' => $headers['accept-encoding'],
            'HTTP_ACCEPT_LANGUAGE' => $headers['accept-language'],
            'HTTP_CONNECTION' => $headers['connection'],
            'HTTP_HOST' => $headers['host'],
            'HTTP_REFERER' => $headers['referer'],
            'HTTP_USER_AGENT' => $headers['user-agent'],
            'PHP_SELF' => '/albums/index.php',
            'REMOTE_ADDR' => '217.254.27.52',
            'REMOTE_PORT' => '73231',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'REQUEST_TIME' => '1696692392',
            'REQUEST_URI' => '/albums',
            'SCRIPT_FILENAME' => '/var/www/albums/index.php',
            'SCRIPT_NAME' => '/albums/index.php',
            'SERVER_ADDR' => '173.230.13.213',
            'SERVER_ADMIN' => 'admin@example.com',
            'SERVER_NAME' => 'www.example.com',
            'SERVER_PORT' => '80',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_SOFTWARE' => 'nginx/1.22.1',
        ], $server);

        $cookie = array_merge([
            'authenticated' => 'true',
        ], $cookie);

        $files = array_merge($this->getFile(), $files);

        $factory = new Psr17Factory();

        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $factory, // ServerRequestFactory
            $factory, // UriFactory
            $factory, // UploadedFileFactory
            $factory  // StreamFactory
        );

        $request = $creator->fromArrays($server, $headers, $cookie, $get, $post, $files, $body);

        return $request;
    }

    public function set(string $method, array $values): void
    {
        global $_GET;
        global $_POST;
        global $_COOKIE;

        foreach ($values as $key => $value) {
            if (strtoupper($method) === 'GET') {
                $_GET[$key] = $value;

                continue;
            }

            if (strtoupper($method) === 'POST') {
                $_POST[$key] = $value;

                continue;
            }

            if (strtoupper($method) === 'COOKIE') {
                $_COOKIE[$key] = $value;
            } else {
                throw new ValueError("Invalid method '{$method}'");
            }
        }
    }

    public function setQueryString(string $qs): void
    {
        $_SERVER['QUERY_STRING'] = $qs;
    }

    public function enableHttps(?string $serverKey = null): void
    {
        if ($serverKey) {
            $_SERVER[$serverKey] = 'https';
        } else {
            $_SERVER['HTTPS'] = 'on';
        }
    }

    public function disableHttps(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function setMethod(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    }

    public function setContentType(string $contentType): void
    {
        $_SERVER['HTTP_CONTENT_TYPE'] = $contentType;
    }

    public function setRequestUri(string $url): void
    {
        if (substr($url, 0, 1) === '/') {
            $_SERVER['REQUEST_URI'] = $url;
        } else {
            $_SERVER['REQUEST_URI'] = "/{$url}";
        }
    }

    protected function getFile(): array
    {
        return [
            'myfile' => [
                'error' => UPLOAD_ERR_OK,
                'name' => '../malic/chuck-test-file.php',
                'size' => 123,
                'tmp_name' => __FILE__,
                'type' => 'text/plain',
            ],
            'failingfile' => [
                'error' => UPLOAD_ERR_PARTIAL,
                'name' => 'chuck-failing-test-file.php',
                'size' => 123,
                'tmp_name' => '',
                'type' => 'text/plain',
            ],
            'nested' => [
                'myfile' => [
                    'error' => UPLOAD_ERR_OK,
                    'name' => '../malic/chuck-test-file.php',
                    'size' => 123,
                    'tmp_name' => __FILE__,
                    'type' => 'text/plain',
                ],
            ],
        ];
    }

    protected function getFiles(): array
    {
        return [
            'myfile' => [
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL],
                'name' => ['test.php', 'test2.php'],
                'size' => [123, 234],
                'tmp_name' => [__FILE__, __FILE__],
                'type' => ['text/plain', 'text/plain'],
            ],
            'nested' => [
                'myfile' => [
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL],
                    'name' => ['test.php', 'test2.php'],
                    'size' => [123, 234],
                    'tmp_name' => [__FILE__, __FILE__],
                    'type' => ['text/plain', 'text/plain'],
                ],
            ],
        ];
    }
}
