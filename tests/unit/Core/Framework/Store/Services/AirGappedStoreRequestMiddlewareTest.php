<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AirGappedStoreRequestMiddleware;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AirGappedStoreRequestMiddleware::class)]
class AirGappedStoreRequestMiddlewareTest extends TestCase
{
    public function testPassesThroughWhenAirGappedModeIsDisabled(): void
    {
        $response = new Response(200, [], '{"ok":true}');
        $request = new Psr7Request('GET', 'https://api.shopware.com/swplatform/login');
        $handlerCalled = false;

        $middleware = new AirGappedStoreRequestMiddleware(new AirGappedMode(false));

        $handler = function (RequestInterface $req, array $options) use ($response, &$handlerCalled): PromiseInterface {
            $handlerCalled = true;

            return new FulfilledPromise($response);
        };

        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);
        $handledResponse = $promise->wait();

        static::assertTrue($handlerCalled);
        static::assertSame($response, $handledResponse);
    }

    public function testRejectsRequestWhenAirGappedModeIsEnabled(): void
    {
        $request = new Psr7Request('GET', 'https://api.shopware.com/swplatform/login');

        $middleware = new AirGappedStoreRequestMiddleware(new AirGappedMode(true));

        $handler = function (RequestInterface $req, array $options): PromiseInterface {
            static::fail('Store handler must not run in air-gapped mode');
        };

        $this->expectExceptionObject(FrameworkException::airGapped());
        ($middleware($handler))($request, []);
    }
}
