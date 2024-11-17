<?php

declare(strict_types=1);

namespace FiveOrbs\Http\Tests;

use FiveOrbs\Http\Middleware;
use FiveOrbs\Http\Request;
use FiveOrbs\Http\Response;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareTest extends TestCase
{
	public function testMiddleware(): void
	{
		$factory = new Psr17Factory();
		$creator = new ServerRequestCreator(
			$factory, // ServerRequestFactory
			$factory, // UriFactory
			$factory, // UploadedFileFactory
			$factory,  // StreamFactory
		);
		$request = $creator->fromGlobals();
		$rh = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$factory = new Psr17Factory();

				return $factory->createResponse()->withBody(
					$factory->createStream('test:' . $request->getAttribute('test')),
				);
			}
		};
		$mw = new class extends Middleware {
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

		$this->assertSame('test:value:after', (string) $response->getBody());
	}
}
