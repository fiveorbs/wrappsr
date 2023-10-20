<?php

declare(strict_types=1);

namespace Conia\Http\Tests;

use Conia\Http\Exception\RuntimeException;
use Conia\Http\Response;
use Conia\Http\Tests\Setup\C;
use Nyholm\Psr7\Stream;

/**
 * @internal
 *
 * @covers \Conia\Http\Request
 */
final class ResponseTest extends TestCase
{
    public function testGetSetPsr7Response(): void
    {
        $psr = $this->psrResponse();
        $response = new Response($psr);

        $this->assertEquals($psr, $response->psr());

        $response->setPsr($this->psrResponse());

        expect($response->psr())->not->toBe($psr);
    }

    public function testGetStatusCode(): void
    {
        $response = new Response($this->psrResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
    }

    public function testSetStatusCode(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response->status(404);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testSetStatusCodeAndReasonPhrase(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response->status(404, 'Nothing to see');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Nothing to see', $response->getReasonPhrase());
    }

    public function testProtocolVersion(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());

        $this->assertEquals('1.1', $response->getProtocolVersion());

        $response->protocolVersion('2.0');

        $this->assertEquals('2.0', $response->getProtocolVersion());
    }

    public function testCreateWithStringBody(): void
    {
        $text = 'text';
        $response = (new Response($this->psrResponse(), $this->factory()))->write($text);
        $this->assertEquals($text, (string)$response->getBody());
    }

    public function testSetBody(): void
    {
        $stream = $this->factory()->stream('Chuck text');
        $response = new Response($this->psrResponse());
        $response->body($stream);
        $this->assertEquals('Chuck text', (string)$response->getBody());
    }

    public function testFailSettingBodyWithoutFactory(): void
    {
        $this->throws(RuntimeException::class, 'No factory');

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, 'Chuck resource');
        $response = new Response($this->psrResponse());
        $response->body('fails');
    }

    public function testInitWithHeader(): void
    {
        $response = new Response($this->psrResponse());
        $response->header('header-value', 'value');

        $this->assertEquals(true, $response->hasHeader('Header-Value'));
    }

    public function testGetHeader(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response = $response->header('header-value', 'value');

        $this->assertEquals('value', $response->getHeader('Header-Value')[0]);
    }

    public function testRemoveHeader(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response->header('header-value', 'value');

        $this->assertEquals(true, $response->hasHeader('Header-Value'));

        $response = $response->removeHeader('header-value');

        $this->assertEquals(false, $response->hasHeader('Header-Value'));
    }

    public function testRedirectTemporary(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response->redirect('/chuck');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/chuck', $response->getHeader('Location')[0]);
    }

    public function testRedirectPermanent(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response->redirect('/chuck', 301);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/chuck', $response->getHeader('Location')[0]);
    }

    public function testPsr7MessageWrapperMethods(): void
    {
        $response = new Response($this->psrResponse(), $this->factory());
        $response->withProtocolVersion('2.0')
            ->withHeader('test-header', 'test-value')
            ->withHeader('test-header', 'test-value-replaced')
            ->withAddedHeader('test-header', 'test-value-added');

        $origBody = $response->getBody();
        $newBody = Stream::create('chuck');
        $response->withBody($newBody);

        $this->assertEquals('', (string)$origBody);
        $this->assertEquals('chuck', (string)$newBody);
        $this->assertEquals($newBody, $response->getBody());
        $this->assertEquals('2.0', $response->getProtocolVersion());
        $this->assertEquals(2, count($response->getHeaders()['test-header']));
        $this->assertEquals('test-value-replaced', $response->getHeaders()['test-header'][0]);
        $this->assertEquals('test-value-added', $response->getHeaders()['test-header'][1]);
        $this->assertEquals('test-value-added', $response->getHeader('test-header')[1]);
        $this->assertEquals('test-value-replaced, test-value-added', $response->getHeaderLine('test-header'));

        $this->assertEquals(true, $response->hasHeader('test-header'));
        $response->withoutHeader('test-header');
        $this->assertEquals(false, $response->hasHeader('test-header'));
    }

    public function testHtmlResponse(): void
    {
        $response = Response::fromFactory($this->factory());
        $response = $response->html('<h1>Chuck string</h1>');

        $this->assertEquals('<h1>Chuck string</h1>', (string)$response->getBody());
        $this->assertEquals('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testHtmlResponseFromResource(): void
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, '<h1>Chuck resource</h1>');
        $response = Response::fromFactory($this->factory())->html($fh);

        $this->assertEquals('<h1>Chuck resource</h1>', (string)$response->getBody());
        $this->assertEquals('text/html', $response->getHeader('Content-Type')[0]);
    }

    public function testHtmlResponseFromStringable(): void
    {
        $response = Response::fromFactory($this->factory())->html(new class () {
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

        Response::fromFactory($this->factory())->html(new stdClass());
    }

    public function testTextResponse(): void
    {
        $response = Response::fromFactory($this->factory())->text('text');

        $this->assertEquals('text', (string)$response->getBody());
        $this->assertEquals('text/plain', $response->getHeader('Content-Type')[0]);
    }

    public function testJsonResponse(): void
    {
        $response = Response::fromFactory($this->factory())->json([1, 2, 3]);

        $this->assertEquals('[1,2,3]', (string)$response->getBody());
        $this->assertEquals('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testJsonResponseTraversable(): void
    {
        $response = Response::fromFactory($this->factory())
            ->json(_testJsonRendererIterator());

        $this->assertEquals('[13,31,73]', (string)$response->getBody());
        $this->assertEquals('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testFileResponse(): void
    {
        $file = C::root() . '/public/static/image.jpg';
        $response = Response::fromFactory($this->factory())->file($file);

        $this->assertEquals('image/jpeg', $response->getHeader('Content-Type')[0]);
        $this->assertEquals((string)filesize($file), $response->getHeader('Content-Length')[0]);
    }

    public function testFileDownloadResponse(): void
    {
        $file = C::root() . '/public/static/image.jpg';
        $response = Response::fromFactory($this->factory())->download($file);

        $this->assertEquals('image/jpeg', $response->getHeader('Content-Type')[0]);
        $this->assertEquals((string)filesize($file), $response->getHeader('Content-Length')[0]);
        $this->assertEquals('attachment; filename="image.jpg"', $response->getHeader('Content-Disposition')[0]);
    }

    public function testFileDownloadResponseWithChangedName(): void
    {
        $file = C::root() . '/public/static/image.jpg';
        $response = Response::fromFactory($this->factory())->download($file, 'newname.jpg');

        $this->assertEquals('image/jpeg', $response->getHeader('Content-Type')[0]);
        $this->assertEquals((string)filesize($file), $response->getHeader('Content-Length')[0]);
        $this->assertEquals('attachment; filename="newname.jpg"', $response->getHeader('Content-Disposition')[0]);
    }

    public function testSendfileResponse(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx';

        $file = C::root() . '/public/static/image.jpg';
        $response = Response::fromFactory($this->factory())->sendfile($file);

        $this->assertEquals($file, $response->getHeader('X-Accel-Redirect')[0]);

        $_SERVER['SERVER_SOFTWARE'] = 'apache';

        $response = Response::fromFactory($this->factory())->sendfile($file);

        $this->assertEquals($file, $response->getHeader('X-Sendfile')[0]);

        unset($_SERVER['SERVER_SOFTWARE']);
    }

    public function testFileResponseNonexistentFileWithRuntimeError(): void
    {
        $this->throws(RuntimeException::class, 'File not found');

        $file = C::root() . '/public/static/pixel.jpg';
        Response::fromFactory($this->factory())->file($file, throwNotFound: false);
    }
}
