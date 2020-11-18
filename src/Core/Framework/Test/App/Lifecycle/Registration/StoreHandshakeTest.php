<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle\Registration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Util\Random;

class StoreHandshakeTest extends TestCase
{
    public function testUrlContainsAllNecessaryElements(): void
    {
        $shopUrl = 'test.shop.com';
        $appEndpoint = 'https://test.com/install';
        $shopId = Random::getAlphanumericString(12);

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('signPayloadWithAppSecret')
            ->willReturn('1234');

        $handshake = new StoreHandshake($shopUrl, $appEndpoint, '', $shopId, $storeClientMock);

        $request = $handshake->assembleRequest();
        static::assertStringStartsWith($appEndpoint, (string) $request->getUri());

        $queryParams = [];
        \parse_str($request->getUri()->getQuery(), $queryParams);

        static::assertArrayHasKey('shop-url', $queryParams);
        static::assertEquals(urlencode($shopUrl), $queryParams['shop-url']);

        static::assertArrayHasKey('shop-id', $queryParams);
        static::assertEquals($shopId, $queryParams['shop-id']);

        static::assertArrayHasKey('timestamp', $queryParams);
        static::assertNotEmpty((string) $queryParams['timestamp']);

        static::assertTrue($request->hasHeader('shopware-app-signature'));
        static::assertEquals(
            '1234',
            $request->getHeaderLine('shopware-app-signature')
        );
    }

    public function testAppProof(): void
    {
        $shopUrl = 'test.shop.com';
        $appEndpoint = 'https://test.com/install';
        $appName = 'testapp';
        $shopId = Random::getAlphanumericString(12);

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('signPayloadWithAppSecret')
            ->with($shopId . $shopUrl . $appName, $appName)
            ->willReturn('1234');

        $handshake = new StoreHandshake($shopUrl, $appEndpoint, $appName, $shopId, $storeClientMock);

        static::assertEquals('1234', $handshake->fetchAppProof());
    }
}
