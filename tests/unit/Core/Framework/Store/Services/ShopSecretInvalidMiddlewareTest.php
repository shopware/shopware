<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Exception\ShopSecretInvalidException;
use Shopware\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
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

        $middleware = new ShopSecretInvalidMiddleware(
            $this->createMock(Connection::class),
            $this->createMock(SystemConfigService::class)
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    public function testKeepsStoreTokensAndReturnsResponseWithRewoundBody(): void
    {
        $response = new Response(401, [], '{"payload":"data"}');

        $middleware = new ShopSecretInvalidMiddleware(
            $this->createMock(Connection::class),
            $this->createMock(SystemConfigService::class)
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    public function testThrowsAndDeletesStoreTokensIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-68"}');

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('executeStatement');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('delete')
            ->with(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET);

        $middleware = new ShopSecretInvalidMiddleware(
            $connection,
            $systemConfigService
        );

        $this->expectException(ShopSecretInvalidException::class);
        $middleware($response);
    }
}
