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

        $this->assertSame($psr, $response->unwrap());

        $response->wrap($this->response());

        $this->assertNotSame($psr, $response->unwrap());
    }

    public function testGetStatusCode(): void
    {
        $response = new Response($this->response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
    }

    public function testSetStatusCode(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->status(404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testSetStatusCodeAndReasonPhrase(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->status(404, 'Nothing to see');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Nothing to see', $response->getReasonPhrase());
    }

    public function testProtocolVersion(): void
    {
        $response = new Response($this->response(), $this->streamFactory());

        $this->assertSame('1.1', $response->getProtocolVersion());

        $response->protocolVersion('2.0');

        $this->assertSame('2.0', $response->getProtocolVersion());
    }

    public function testCreateWithStringBody(): void
    {
        $text = 'text';
        $response = (new Response($this->response(), $this->streamFactory()))->write($text);
        $this->assertSame($text, (string)$response->getBody());
    }

    public function testSetBodyWithStream(): void
    {
        $stream = $this->streamFactory()->createStream('Chuck text stream');
        $response = new Response($this->response());
        $response->body($stream);
        $this->assertSame('Chuck text stream', (string)$response->getBody());
    }

    public function testSetBodyWithString(): void
    {
        $response = new Response($this->response());
        $response->body('Chuck text string');
        $this->assertSame('Chuck text string', (string)$response->getBody());
    }

    public function testSetBodyWithStringUsingFactory(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->body('Chuck text using factory');
        $this->assertSame('Chuck text using factory', (string)$response->getBody());
    }

    public function testFailSettingStringBodyWithoutFactory(): void
    {
        $this->throws(RuntimeException::class, 'not writable');

        $fh = fopen('php://temp', 'r');
        $stream = $this->streamFactory()->createStreamFromResource($fh);
        $response = new Response($this->response());
        $response->body($stream);
        $response->body('try to overwrite');
    }

    public function testInitWithHeader(): void
    {
        $response = new Response($this->response());
        $response->header('header-value', 'value');

        $this->assertSame(true, $response->hasHeader('Header-Value'));

        $headers = $response->headers();
        $this->assertSame('value', $headers['header-value'][0]);
    }

    public function testGetHeader(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response = $response->header('header-value', 'value');

        $this->assertSame('value', $response->getHeader('Header-Value')[0]);
    }

    public function testRemoveHeader(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->header('header-value', 'value');

        $this->assertSame(true, $response->hasHeader('Header-Value'));

        $response = $response->removeHeader('header-value');

        $this->assertSame(false, $response->hasHeader('Header-Value'));
    }

    public function testRedirectTemporary(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->redirect('/chuck');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/chuck', $response->getHeader('Location')[0]);
    }

    public function testRedirectPermanent(): void
    {
        $response = new Response($this->response(), $this->streamFactory());
        $response->redirect('/chuck', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/chuck', $response->getHeader('Location')[0]);
    }

    public function testWithContentTypeFromResource(): void
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, '<h1>Chuck resource</h1>');
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())
            ->withContentType('text/html', $fh, 404, 'The Phrase');

        $this->assertSame('<h1>Chuck resource</h1>', (string)$response->getBody());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testWithContentTypeFromString(): void
    {
        $response = (new Response($this->response()))->withContentType(
            'text/html',
            '<h1>Chuck String</h1>',
            404,
            'The Phrase'
        );

        $this->assertSame('<h1>Chuck String</h1>', (string)$response->getBody());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testWithContentTypeFromStream(): void
    {
        $stream = $this->streamFactory()->createStream('<h1>Chuck Stream</h1>');
        $response = (new Response($this->response()))->withContentType('text/html', $stream, 404, 'The Phrase');

        $this->assertSame('<h1>Chuck Stream</h1>', (string)$response->getBody());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testWithContentTypeFromStringable(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->withContentType(
            'text/html',
            new class () {
                public function __toString(): string
                {
                    return '<h1>Chuck Stringable</h1>';
                }
            },
            404,
            'The Phrase'
        );

        $this->assertSame('<h1>Chuck Stringable</h1>', (string)$response->getBody());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testFailingWithContentTypeFromResource(): void
    {
        $this->throws(RuntimeException::class, 'No factory available');

        $fh = fopen('php://temp', 'r+');
        (new Response($this->response()))->withContentType('text/html', $fh, 404, 'The Phrase');
    }

    public function testWithContentTypeInvalidData(): void
    {
        $this->throws(RuntimeException::class, 'strings, Stringable or resources');

        Response::fromFactory($this->responseFactory(), $this->streamFactory())->html(new stdClass());
    }

    public function testHtmlResponse(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory());
        $response = $response->html('<h1>Chuck string</h1>');

        $this->assertSame('<h1>Chuck string</h1>', (string)$response->getBody());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testTextResponse(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->text('text');

        $this->assertSame('text', (string)$response->getBody());
        $this->assertSame('text/plain', $response->getHeader('Content-Type')[0]);
    }

    public function testJsonResponse(): void
    {
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->json([1, 2, 3]);

        $this->assertSame('[1,2,3]', (string)$response->getBody());
        $this->assertSame('application/json', $response->getHeader('Content-Type')[0]);
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

        $this->assertSame('[13,31,73]', (string)$response->getBody());
        $this->assertSame('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testFileResponse(): void
    {
        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->file($file);

        $this->assertSame('image/webp', $response->getHeader('Content-Type')[0]);
        $this->assertSame((string)filesize($file), $response->getHeader('Content-Length')[0]);
    }

    public function testFileDownloadResponse(): void
    {
        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->download($file);

        $this->assertSame('image/webp', $response->getHeader('Content-Type')[0]);
        $this->assertSame((string)filesize($file), $response->getHeader('Content-Length')[0]);
        $this->assertSame('attachment; filename="image.webp"', $response->getHeader('Content-Disposition')[0]);
    }

    public function testFileDownloadResponseWithChangedName(): void
    {
        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory(
            $this->responseFactory(),
            $this->streamFactory()
        )->download($file, 'newname.jpg');

        $this->assertSame('image/webp', $response->getHeader('Content-Type')[0]);
        $this->assertSame((string)filesize($file), $response->getHeader('Content-Length')[0]);
        $this->assertSame('attachment; filename="newname.jpg"', $response->getHeader('Content-Disposition')[0]);
    }

    public function testSendfileResponse(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx';

        $file = self::FIXTURES . '/image.webp';
        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->sendfile($file);

        $this->assertSame($file, $response->getHeader('X-Accel-Redirect')[0]);

        $_SERVER['SERVER_SOFTWARE'] = 'apache';

        $response = Response::fromFactory($this->responseFactory(), $this->streamFactory())->sendfile($file);

        $this->assertSame($file, $response->getHeader('X-Sendfile')[0]);

        unset($_SERVER['SERVER_SOFTWARE']);
    }

    public function testFileResponseNonexistentFileWithRuntimeError(): void
    {
        $this->throws(FileNotFoundException::class, 'File not found');

        $file = self::FIXTURES . '/public/static/pixel.jpg';
        Response::fromFactory($this->responseFactory(), $this->streamFactory())->file($file);
    }
}
