<?php

declare(strict_types=1);

namespace Conia\Http\Tests;

use Conia\Http\Factory\Guzzle;
use Conia\Http\Middleware;
use Conia\Http\Request;
use Conia\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @covers \Conia\Http\Middleware
 */
final class MiddlewareTest extends TestCase
{
    public function testMiddleware(): void
    {
        $request = (new Guzzle())->request();
        $rh = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $factory = new Guzzle();

                return $factory->response()->withBody($factory->stream('test:' . $request->getAttribute('test')));
            }
        };
        $mw = new class () extends Middleware {
            public function handle(Request $request, callable $next): Response
            {
                $request->set('test', 'value');

                $response = $next($request);
                $body = $response->getBody();
                $content = $body->getContents();
                $body->rewind();
                $body->write($content . ':after');
                $response->body($body);

                return $response;
            }
        };
        $response = $mw->process($request, $rh);

        $this->assertEquals('test:value:after', (string)$response->getBody());
    }
}
