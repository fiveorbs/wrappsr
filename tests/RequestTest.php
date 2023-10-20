<?php

declare(strict_types=1);

namespace Conia\Http\Tests;

use Conia\Http\Exception\OutOfBoundsException;
use Conia\Http\Exception\RuntimeException;
use Conia\Http\Request;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;

/**
 * @internal
 *
 * @covers \Conia\Http\Request
 */
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
    }

    public function testRequestParam(): void
    {
        $request = new Request($this->request(get: [
            'chuck' => 'schuldiner',
            'born' => '1967',
        ]));

        $this->assertEquals('schuldiner', $request->param('chuck'));
        $this->assertEquals('1967', $request->param('born'));
    }

    public function testRequestHeader(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('deflate, gzip;q=1.0, *;q=0.5', $request->header('Accept-Encoding'));
        $this->assertEquals('', $request->header('Does-Not-Exist'));
    }

    public function testRequestHeaders(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('www.example.com', $request->headers()['Host'][0]);
        $this->assertEquals('deflate, gzip;q=1.0, *;q=0.5', $request->headers()['Accept-Encoding'][0]);
    }

    public function testRequestHeadersFirstEntryOnly(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('www.example.com', $request->headers(firstOnly: true)['Host']);
        $this->assertEquals('deflate, gzip;q=1.0, *;q=0.5', $request->headers(firstOnly: true)['Accept-Encoding']);
    }

    public function testRequestAccept(): void
    {
        $request = new Request($this->request());

        $this->assertEquals([
            'text/html', 'application/xhtml+xml', 'text/plain', ], $request->accept());
    }

    public function testRequestParamDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->param('doesnotexist', 'the default'));
    }

    public function testRequestParamFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Query string');

        $request = new Request($this->request());

        $this->assertEquals(null, $request->param('doesnotexist'));
    }

    public function testRequestParams(): void
    {
        $this->set('GET', ['chuck' => 'schuldiner', 'born' => '1967']);
        $request = new Request($this->request());
        $params = $request->params();

        $this->assertEquals(2, count($params));
        $this->assertEquals('1967', $params['born']);
        $this->assertEquals('schuldiner', $params['chuck']);
    }

    public function testRequestField(): void
    {
        $this->setContentType('application/x-www-form-urlencoded');
        $this->setMethod('POST');
        $this->set('POST', ['chuck' => 'schuldiner', 'born' => '1967']);
        $request = new Request($this->request());

        $this->assertEquals('schuldiner', $request->field('chuck'));
        $this->assertEquals('1967', $request->field('born'));
    }

    public function testRequestFieldDefaultPOSTIsNull(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->field('doesnotexist', 'the default'));
    }

    public function testRequestFieldDefaultPOSTIsArray(): void
    {
        $this->setContentType('application/x-www-form-urlencoded');
        $this->setMethod('POST');
        $this->set('POST', ['chuck' => 'schuldiner']);
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->field('doesnotexist', 'the default'));
    }

    public function testRequestFieldFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Form field');

        $this->setContentType('application/x-www-form-urlencoded');
        $this->setMethod('POST');
        $request = new Request($this->request());

        $request->field('doesnotexist');
    }

    public function testRequestForm(): void
    {
        $this->setContentType('application/x-www-form-urlencoded');
        $this->setMethod('POST');
        $this->set('POST', ['first_band' => 'Mantas', 'chuck' => 'schuldiner']);
        $request = new Request($this->request());

        $this->assertEquals([ 'first_band' => 'Mantas', 'chuck' => 'schuldiner', ], $request->form());
    }

    public function testRequestCookie(): void
    {
        $this->set('COOKIE', ['chuck' => 'schuldiner', 'born' => '1967']);
        $request = new Request($this->request());

        $this->assertEquals('schuldiner', $request->cookie('chuck'));
        $this->assertEquals('1967', $request->cookie('born'));
    }

    public function testRequestCookieDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->cookie('doesnotexist', 'the default'));
    }

    public function testRequestCookieFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Cookie');

        $request = new Request($this->request());

        $request->cookie('doesnotexist')->toBe(null);
    }

    public function testRequestCookies(): void
    {
        $this->set('COOKIE', ['chuck' => 'schuldiner', 'born' => '1967']);
        $request = new Request($this->request());
        $cookies = $request->cookies();

        $this->assertEquals(2, count($cookies));
        $this->assertEquals('1967', $cookies['born']);
        $this->assertEquals('schuldiner', $cookies['chuck']);
    }

    public function testRequestServer(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('www.example.com', $request->server('HTTP_HOST'));
        $this->assertEquals('HTTP/1.1', $request->server('SERVER_PROTOCOL'));
    }

    public function testRequestServerDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->server('doesnotexist', 'the default'));
    }

    public function testRequestServerFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Server');

        $request = new Request($this->request());

        $this->assertEquals(null, $request->server('doesnotexist'));
    }

    public function testRequestServers(): void
    {
        $request = new Request($this->request());
        $params = $request->serverParams();

        $this->assertEquals('www.example.com', $params['HTTP_HOST']);
        $this->assertEquals('HTTP/1.1', $params['SERVER_PROTOCOL']);
    }

    public function testRequestGetDefault(): void
    {
        $request = new Request($this->request());

        $this->assertEquals('the default', $request->get('doesnotexist', 'the default'));
    }

    public function testRequestGetFailing(): void
    {
        $this->throws(OutOfBoundsException::class, 'Request attribute');

        $request = new Request($this->request());

        $this->assertEquals(null, $request->get('doesnotexist'));
    }

    public function testRequestAttributes(): void
    {
        $request = new Request($this->request());
        $request->withAttribute('one', 1)->set('two', '2');

        $this->assertEquals(2, count($request->attributes()));
        $this->assertEquals(1, $request->get('one'));
        $this->assertEquals('2', $request->get('two'));
    }

    public function testRequestBody(): void
    {
        $this->assertEquals('', (string)(new Request($this->request()))->body());
    }

    public function testRequestJson(): void
    {
        $stream = Stream::create('[{"title": "Leprosy", "released": 1988}, {"title": "Human", "released": 1991}]');
        $request = new Request($this->request()->withBody($stream));

        $this->assertEquals([ ['title' => 'Leprosy', 'released' => 1988],
            ['title' => 'Human', 'released' => 1991], ], $request->json());
    }

    public function testRequestJsonEmpty(): void
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
        $request->setPsr($psr);

        $this->assertEquals($psr, $request->psr());
    }

    public function testPsr7MessageWrapperMethods(): void
    {
        $request = new Request($this->request()
            ->withProtocolVersion('2.0')
            ->withHeader('test-header', 'test-value')
            ->withHeader('test-header', 'test-value-replaced')
            ->withAddedHeader('test-header', 'test-value-added'));

        $origBody = $request->getBody();
        $newBody = Stream::create('chuck');
        $request->withBody($newBody);

        $this->assertEquals('', (string)$origBody);
        $this->assertEquals('chuck', (string)$newBody);
        $this->assertEquals($newBody, $request->getBody());
        $this->assertEquals('2.0', $request->getProtocolVersion());
        $this->assertEquals(2, count($request->getHeaders()['test-header']));
        $this->assertEquals('test-value-replaced', $request->getHeaders()['test-header'][0]);
        $this->assertEquals('test-value-added', $request->getHeaders()['test-header'][1]);
        $this->assertEquals('test-value-added', $request->getHeader('test-header')[1]);
        $this->assertEquals('test-value-replaced, test-value-added', $request->getHeaderLine('test-header'));

        $this->assertEquals(true, $request->hasHeader('test-header'));
        $request->withoutHeader('test-header');
        $this->assertEquals(false, $request->hasHeader('test-header'));
    }

    public function testPsr7ServerRequestWrapperMethods(): void
    {
        $request = new Request($this->request());
        $request->withMethod('PUT');
        $request->withRequestTarget('/chuck');
        $request->withQueryParams(['get' => 'get']);
        $request->withParsedBody(['post' => 'post']);
        $request->withCookieParams(['cookie' => 'cookie']);
        $request->withUri(new Uri('http://www.newexample.com'));
        $request->withAttribute('attribute', 'attribute');
        $request->withUploadedFiles([
            'myfile' => [
                'error' => UPLOAD_ERR_OK,
                'name' => '../malic/chuck-test-file.php',
                'size' => 123,
                'tmp_name' => __FILE__,
                'type' => 'text/plain',
            ],
        ]);

        $this->assertEquals('www.newexample.com', $request->getUri()->getHost());
        $this->assertEquals('HTTP/1.1', $request->getServerParams()['SERVER_PROTOCOL']);
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertEquals('/chuck', $request->getRequestTarget());
        $this->assertEquals('get', $request->getQueryParams()['get']);
        $this->assertEquals('post', $request->getParsedBody()['post']);
        $this->assertEquals('cookie', $request->getCookieParams()['cookie']);
        $this->assertEquals('attribute', $request->getAttributes()['attribute']);
        $this->assertEquals('attribute', $request->getAttribute('attribute'));
        $this->assertEquals(true, isset($request->getUploadedFiles()['myfile']));

        $request->withoutAttribute('attribute');

        $this->assertEquals(false, isset($request->getAttributes()['attribute']));
        $this->assertEquals('default', $request->getAttribute('attribute', 'default'));
    }
}
