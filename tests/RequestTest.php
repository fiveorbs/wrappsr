<?php

declare(strict_types=1);

namespace Conia\Http\Tests;

use Conia\Http\Exception\OutOfBoundsException;
use Conia\Http\Exception\RuntimeException;
use Conia\Http\Request;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;

final class RequestTest extends TestCase
{
    public function testHelperMethods(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('GET', $request->method());
        $this->assertEquals(true, $request->isMethod('GET'));
        $this->assertEquals(false, $request->isMethod('POST'));
    }

    public function testUriHelpers(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('/albums', $request->uri()->getPath());
        $this->assertEquals('http://www.example.com/albums', (string)$request->uri());

        $request = new Request($this->request(server: [
            'QUERY_STRING' => 'from=1988&to=1991',
            'REQUEST_URI' => '/albums?from=1988&to=1991',
        ]));

        $this->assertEquals('http://www.example.com/albums?from=1988&to=1991', (string)$request->uri());
        $this->assertEquals('www.example.com', $request->uri()->getHost());
        $this->assertEquals('http://www.example.com', $request->origin());
        $this->assertEquals('/albums?from=1988&to=1991', $request->target());
    }

    public function testParam(): void
    {
        $request = new Request($this->request(get: [
            'chuck' => 'schuldiner',
            'born' => '1967',
        ]));

        $this->assertEquals('schuldiner', $request->param('chuck'));
        $this->assertEquals('1967', $request->param('born'));
    }

    public function testHeader(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('deflate, gzip;q=1.0, *;q=0.5', $request->header('Accept-Encoding'));
        $this->assertEquals('', $request->header('Does-Not-Exist'));
    }

    public function testHeaderArray(): void
    {
        $request = new Request($this->request());

        $this->assertEquals(['deflate, gzip;q=1.0, *;q=0.5'], $request->headerArray('Accept-Encoding'));
        $this->assertEquals([], $request->headerArray('Does-Not-Exist'));
    }

    public function testHeaders(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('www.example.com', $request->headers()['Host'][0]);
        $this->assertEquals('deflate, gzip;q=1.0, *;q=0.5', $request->headers()['Accept-Encoding'][0]);
    }

    public function testHeadersFirstEntryOnly(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('www.example.com', $request->headers(firstOnly: true)['Host']);
        $this->assertEquals('deflate, gzip;q=1.0, *;q=0.5', $request->headers(firstOnly: true)['Accept-Encoding']);
    }

    public function testWritingHeaders(): void
    {
        $request = new Request($this->request());
        $request->setHeader('test-header', 'test-value');
        $request->setHeader('test-header', 'test-value-replaced');
        $request->addHeader('test-header', 'test-value-added');

        $this->assertEquals('test-value-replaced, test-value-added', $request->header('test-header'));
        $this->assertEquals(['test-value-replaced', 'test-value-added'], $request->headerArray('test-header'));

        $request->removeHeader('test-header');

        $this->assertEquals('', $request->header('test-header'));
    }

    public function testParamDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->param('doesnotexist', 'the default'));
    }

    public function testParamFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Query string');

        $request = new Request($this->request());

        $this->assertEquals(null, $request->param('doesnotexist'));
    }

    public function testParams(): void
    {
        $request = new Request($this->request(
            get: ['chuck' => 'schuldiner', 'born' => '1967']
        ));
        $params = $request->params();

        $this->assertEquals(2, count($params));
        $this->assertEquals('1967', $params['born']);
        $this->assertEquals('schuldiner', $params['chuck']);
    }

    public function testField(): void
    {
        $request = new Request($this->request(
            server: [
                'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'REQUEST_METHOD' => 'POST',
            ],
            post: ['chuck' => 'schuldiner', 'born' => '1967']
        ));

        $this->assertEquals('schuldiner', $request->field('chuck'));
        $this->assertEquals('1967', $request->field('born'));
    }

    public function testFieldDefaultPostIsNull(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->field('doesnotexist', 'the default'));
    }

    public function testFieldDefaultPostIsArray(): void
    {
        $request = new Request($this->request(
            server: [
                'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'REQUEST_METHOD' => 'POST',
            ],
            post: ['chuck' => 'schuldiner']
        ));

        $this->assertEquals('the default', $request->field('doesnotexist', 'the default'));
    }

    public function testFieldFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Form field');

        $request = new Request($this->request(server: [
            'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'REQUEST_METHOD' => 'POST',
        ]));

        $request->field('doesnotexist');
    }

    public function testForm(): void
    {
        $request = new Request($this->request(
            server: [
                'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'REQUEST_METHOD' => 'POST',
            ],
            post: [
                'first_band' => 'Mantas',
                'chuck' => 'schuldiner',
            ]
        ));

        $this->assertEquals([ 'first_band' => 'Mantas', 'chuck' => 'schuldiner', ], $request->form());
    }

    public function testCookie(): void
    {
        $request = new Request($this->request(cookie: [
            'chuck' => 'schuldiner',
            'born' => '1967',
        ]));

        $this->assertEquals('schuldiner', $request->cookie('chuck'));
        $this->assertEquals('1967', $request->cookie('born'));
    }

    public function testCookieDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->cookie('doesnotexist', 'the default'));
    }

    public function testCookieFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Cookie');

        $request = new Request($this->request());

        $request->cookie('doesnotexist')->toBe(null);
    }

    public function testCookies(): void
    {
        $request = new Request($this->request(cookie: ['chuck' => 'schuldiner', 'born' => '1967']));
        $cookies = $request->cookies();

        $this->assertEquals(2, count($cookies));
        $this->assertEquals('1967', $cookies['born']);
        $this->assertEquals('schuldiner', $cookies['chuck']);
    }

    public function testServer(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('www.example.com', $request->server('HTTP_HOST'));
        $this->assertEquals('HTTP/1.1', $request->server('SERVER_PROTOCOL'));
    }

    public function testServerDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->server('doesnotexist', 'the default'));
    }

    public function testServerFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Server');

        $request = new Request($this->request());

        $this->assertEquals(null, $request->server('doesnotexist'));
    }

    public function testServerParams(): void
    {
        $request = new Request($this->request());
        $params = $request->serverParams();

        $this->assertEquals('www.example.com', $params['HTTP_HOST']);
        $this->assertEquals('HTTP/1.1', $params['SERVER_PROTOCOL']);
    }

    public function testGetDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->get('doesnotexist', 'the default'));
    }

    public function testGetFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Request attribute');

        $request = new Request($this->request());

        $this->assertEquals(null, $request->get('doesnotexist'));
    }

    public function testAttributes(): void
    {
        $request = new Request($this->request()->withAttribute('one', 1));
        $request->set('two', '2');

        $this->assertEquals(2, count($request->attributes()));
        $this->assertEquals(1, $request->get('one'));
        $this->assertEquals('2', $request->get('two'));
    }

    public function testBody(): void
    {
        $this->assertEquals('', (string)(new Request($this->request()))->body());
    }

    public function testJson(): void
    {
        $stream = Stream::create('[{"title": "Leprosy", "released": 1988}, {"title": "Human", "released": 1991}]');
        $request = new Request($this->request()->withBody($stream));

        $this->assertEquals([ ['title' => 'Leprosy', 'released' => 1988],
            ['title' => 'Human', 'released' => 1991], ], $request->json());
    }

    public function testJsonEmpty(): void
    {
        $request = new Request($this->request());

        $this->assertEquals(null, $request->json());
    }

    public function testGetFileInstance(): void
    {
        $request = new Request($this->request());
        $file = $request->file('myfile');

        $this->assertInstanceOf(PsrUploadedFile::class, $file);
    }

    public function testFailCallingFileWithoutKey(): void
    {
        $this->throws(RuntimeException::class, 'No file key');

        $request = new Request($this->request());
        $request->file();
    }

    public function testGetNestedFileInstance(): void
    {
        $request = new Request($this->request());
        $file = $request->file('nested', 'myfile');

        $this->assertInstanceOf(PsrUploadedFile::class, $file);
    }

    public function testGetAllFiles(): void
    {
        $request = new Request($this->request(files: $this->getFiles()));
        $files = $request->files();

        $this->assertEquals(2, count($files));
        $this->assertEquals(true, isset($files['myfile']));
        $this->assertEquals(true, isset($files['nested']));
    }

    public function testGetFilesInstances(): void
    {
        $request = new Request($this->request(files: $this->getFiles()));
        $files = $request->files('myfile');

        $this->assertEquals(2, count($files));
        $this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
        $this->assertInstanceOf(PsrUploadedFile::class, $files[1]);
    }

    public function testGetNestedFilesInstances(): void
    {
        $request = new Request($this->request(files: $this->getFiles()));
        $files = $request->files('nested', 'myfile');

        $this->assertEquals(2, count($files));
        $this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
        $this->assertInstanceOf(PsrUploadedFile::class, $files[1]);
    }

    public function testGetNestedFilesInstancesUsingAnArray(): void
    {
        $request = new Request($this->request(files: $this->getFiles()));
        $files = $request->files(['nested', 'myfile']);

        $this->assertEquals(2, count($files));
        $this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
        $this->assertInstanceOf(PsrUploadedFile::class, $files[1]);
    }

    public function testGetFilesInstancesWithOnlyOnePresent(): void
    {
        $request = new Request($this->request());
        $files = $request->files('myfile');

        $this->assertEquals(1, count($files));
        $this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
    }

    public function testAccessSingleFileWhenMulitpleAreAvailable(): void
    {
        $this->throws(RuntimeException::class, 'Multiple files');

        $request = new Request($this->request(files: $this->getFiles()));
        $request->file('myfile');
    }

    public function testFileInstanceNotAvailable(): void
    {
        $this->throws(OutOfBoundsException::class, "Invalid file key ['does-not-exist']");

        $request = new Request($this->request());
        $request->file('does-not-exist');
    }

    public function testFileInstanceNotAvailableTooMuchKeys(): void
    {
        $this->throws(OutOfBoundsException::class, "Invalid file key (too deep) ['nested']['myfile']['toomuch']");

        $request = new Request($this->request());
        $request->file('nested', 'myfile', 'toomuch');
    }

    public function testAccessFileUsingMulitpleArrays(): void
    {
        $this->throws(RuntimeException::class, 'Either provide');

        $request = new Request($this->request());
        $request->files([], []);
    }

    public function testNestedFileInstanceNotAvailable(): void
    {
        $this->throws(OutOfBoundsException::class, "Invalid file key ['does-not-exist']['really']");

        $request = new Request($this->request());
        $request->file('does-not-exist', 'really');
    }

    public function testFileInstancesAreNotAvailable(): void
    {
        $this->throws(OutOfBoundsException::class, "Invalid files key ['does-not-exist']");

        $request = new Request($this->request());
        $request->files('does-not-exist');
    }

    public function testNestedFileInstancesAreNotAvailable(): void
    {
        $this->throws(OutOfBoundsException::class, "Invalid files key ['does-not-exist']['really']");

        $request = new Request($this->request());
        $request->files('does-not-exist', 'really');
    }

    public function testGettingAndSettingPsr7Instance(): void
    {
        $psr = $this->request();
        $request = new Request($this->request());
        $request->wrap($psr);

        $this->assertEquals($psr, $request->unwrap());
    }
}
