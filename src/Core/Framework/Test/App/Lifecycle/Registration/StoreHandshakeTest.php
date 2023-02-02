<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle\Registration;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppLicenseCouldNotBeVerifiedException;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Kernel;

/**
 * @internal
 */
class StoreHandshakeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    public function testUrlContainsAllNecessaryElements(): void
    {
        $shopUrl = 'test.shop.com';
        $appEndpoint = 'https://test.com/install';
        $shopId = Random::getAlphanumericString(12);

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('signPayloadWithAppSecret')
            ->willReturn('1234');

        $handshake = new StoreHandshake($shopUrl, $appEndpoint, '', $shopId, $storeClientMock, Kernel::SHOPWARE_FALLBACK_VERSION);

        $request = $handshake->assembleRequest();
        static::assertStringStartsWith($appEndpoint, (string) $request->getUri());

        $queryParams = [];
        parse_str($request->getUri()->getQuery(), $queryParams);

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

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
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

        $handshake = new StoreHandshake($shopUrl, $appEndpoint, $appName, $shopId, $storeClientMock, Kernel::SHOPWARE_FALLBACK_VERSION);

        static::assertEquals('1234', $handshake->fetchAppProof());
    }

    public function testThrowsIfSbpRespondsWithUnauthorized(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('signPayloadWithAppSecret')
            ->willThrowException(new ClientException(
                '',
                new Request('POST', 'app_generate_signature'),
                new Response(401, [], json_encode(['code' => 'ShopwarePlatformException-1']))
            ));

        $handshake = new StoreHandshake(
            'http://shop.url',
            'http://app.url',
            'TestApp',
            'my-shop-id',
            $storeClient,
            Kernel::SHOPWARE_FALLBACK_VERSION
        );

        static::expectException(AppLicenseCouldNotBeVerifiedException::class);

        $handshake->assembleRequest();
    }
}
