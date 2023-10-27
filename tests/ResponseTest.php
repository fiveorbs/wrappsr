<?php

declare(strict_types=1);

namespace Conia\Http\Tests;

use Conia\Http\Exception\FileNotFoundException;
use Conia\Http\Exception\RuntimeException;
use Conia\Http\Response;
use stdClass;

final class ResponseTest extends TestCase
{
    public const FIXTURES = __DIR__ . '/Fixtures';

    public function testGetSetPsr7Response(): void
    {
        $psr = $this->response();
        $response = new Response($psr);

        $this->assertEquals($psr, $response->unwrap());

        $response->wrap($this->response());

        $this->assertNotSame($psr, $response->unwrap());
    }

    public function testGetStatusCode(): void
    {
        $response = new Response($this->response());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
    }

    public function testSetStatusCode(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->status(404);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testSetStatusCodeAndReasonPhrase(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->status(404, 'Nothing to see');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Nothing to see', $response->getReasonPhrase());
    }

    public function testProtocolVersion(): void
    {
        $response = new Response($this->response(), $this->streamFactory());

        $this->assertEquals('1.1', $response->getProtocolVersion());

        $response->protocolVersion('2.0');

        $this->assertEquals('2.0', $response->getProtocolVersion());
    }

    public function testCreateWithStringBody(): void
    {
        $text = 'text';
        $response = (new Response($this->response(), $this->streamFactory()))->write($text);
        $this->assertEquals($text, (string)$response->getBody());
    }

    public function testSetBody(): void
    {
        $stream = $this->streamFactory()->createStream('Chuck text');
        $response = new Response($this->response());
        $response->body($stream);
        $this->assertEquals('Chuck text', (string)$response->getBody());
    }

    public function testFailSettingBodyWithoutFactory(): void
    {
        $this->throws(RuntimeException::class, 'No factory');

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, 'Chuck resource');
        $response = new Response($this->response());
        $response->body('fails');
    }

    public function testInitWithHeader(): void
    {
        $response = new Response($this->response());
        $response->header('header-value', 'value');

        $this->assertEquals(true, $response->hasHeader('Header-Value'));
    }

    public function testGetHeader(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response = $response->header('header-value', 'value');

        $this->assertEquals('value', $response->getHeader('Header-Value')[0]);
    }

    public function testRemoveHeader(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->header('header-value', 'value');

        $this->assertEquals(true, $response->hasHeader('Header-Value'));

        $response = $response->removeHeader('header-value');

        $this->assertEquals(false, $response->hasHeader('Header-Value'));
    }

    public function testRedirectTemporary(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->redirect('/chuck');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/chuck', $response->getHeader('Location')[0]);
    }

    public function testRedirectPermanent(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->redirect('/chuck', 301);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/chuck', $response->getHeader('Location')[0]);
    }

    public function testHtmlResponse(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory());
        $response = $response->html('<h1>Chuck string</h1>');

        $this->assertEquals('<h1>Chuck string</h1>', (string)$response->getBody());
        $this->assertEquals('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testHtmlResponseFromResource(): void
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, '<h1>Chuck resource</h1>');
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->html($fh);

        $this->assertEquals('<h1>Chuck resource</h1>', (string)$response->getBody());
        $this->assertEquals('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testHtmlResponseFromStringable(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->html(new class () {
            public function __toString(): string
            {
                return '<h1>Chuck Stringable</h1>';
            }
        });

        $this->assertEquals('<h1>Chuck Stringable</h1>', (string)$response->getBody());
        $this->assertEquals('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testHtmlResponseInvalidData(): void
    {
        $this->throws(RuntimeException::class, 'strings, Stringable or resources');

        Response::fromFactory($this->responseFactory(), $this->streamFactory())->html(new stdClass());
    }

    public function testTextResponse(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->text('text');

        $this->assertEquals('text', (string)$response->getBody());
        $this->assertEquals('text/plain', $response->getHeader('Content-Type')[0]);
    }

    public function testJsonResponse(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->json([1, 2, 3]);

        $this->assertEquals('[1,2,3]', (string)$response->getBody());
        $this->assertEquals('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testJsonResponseTraversable(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())
            ->json(
                (function () {
                    $arr = [13, 31, 73];

                    foreach ($arr as $a) {
                        yield $a;
                    }
                })()
            );

        $this->assertEquals('[13,31,73]', (string)$response->getBody());
        $this->assertEquals('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testFileResponse(): void
    {
        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->file($file);

        $this->assertEquals('image/webp', $response->getHeader('Content-Type')[0]);
        $this->assertEquals((string)filesize($file), $response->getHeader('Content-Length')[0]);
    }

    public function testFileDownloadResponse(): void
    {
        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->download($file);

        $this->assertEquals('image/webp', $response->getHeader('Content-Type')[0]);
        $this->assertEquals((string)filesize($file), $response->getHeader('Content-Length')[0]);
        $this->assertEquals('attachment; filename="image.webp"', $response->getHeader('Content-Disposition')[0]);
    }

    public function testFileDownloadResponseWithChangedName(): void
    {
        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->download($file, 'newname.jpg');

        $this->assertEquals('image/webp', $response->getHeader('Content-Type')[0]);
        $this->assertEquals((string)filesize($file), $response->getHeader('Content-Length')[0]);
        $this->assertEquals('attachment; filename="newname.jpg"', $response->getHeader('Content-Disposition')[0]);
    }

    public function testSendfileResponse(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx';

        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->sendfile($file);

        $this->assertEquals($file, $response->getHeader('X-Accel-Redirect')[0]);

        $_SERVER['SERVER_SOFTWARE'] = 'apache';

        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->sendfile($file);

        $this->assertEquals($file, $response->getHeader('X-Sendfile')[0]);

        unset($_SERVER['SERVER_SOFTWARE']);
    }

    public function testFileResponseNonexistentFileWithRuntimeError(): void
    {
        $this->throws(FileNotFoundException::class, 'File not found');

        $file = self::FIXTURES . '/public/static/pixel.jpg';
        Response::fromFactory($this->responseFactory(), $this->streamFactory())->file($file);
    }
}
