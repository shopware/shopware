<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShopSecretInvalidMiddleware::class)]
class ShopSecretInvalidMiddlewareTest extends TestCase
{
    public function testKeepsStoreTokensAndReturnsResponse(): void
    {
        $response = new Response(200, [], '{"payload":"data"}');
        $request = new Request('GET', '/');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('delete');

        $middleware = new ShopSecretInvalidMiddleware($connection, $systemConfigService);

        $handledResponse = $this->invoke($middleware, $response, $request);

        static::assertSame($response, $handledResponse);
    }

    public function testKeepsStoreTokensAndReturnsResponseWithRewoundBody(): void
    {
        $response = new Response(401, [], '{"payload":"data"}');
        $request = new Request('GET', '/');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('delete');

        $middleware = new ShopSecretInvalidMiddleware($connection, $systemConfigService);

        $handledResponse = $this->invoke($middleware, $response, $request);

        static::assertSame($response, $handledResponse);
        static::assertSame('{"payload":"data"}', (string) $handledResponse->getBody());
    }

    public function testThrowsAndDeletesStoreTokensIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-68"}');
        $request = new Request('GET', '/');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('delete')
            ->with(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET);

        $middleware = new ShopSecretInvalidMiddleware($connection, $systemConfigService);

        $this->expectExceptionObject(StoreException::shopSecretInvalid());
        $this->invoke($middleware, $response, $request);
    }

    private function invoke(ShopSecretInvalidMiddleware $middleware, Response $response, Request $request): mixed
    {
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);

        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);

        return $promise->wait();
    }
}
