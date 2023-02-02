<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle\Registration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Kernel;

/**
 * @internal
 */
class PrivateHandshakeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUrlContainsAllNecessaryElements(): void
    {
        $shopUrl = 'test.shop.com';
        $secret = 's3cr3t';
        $appEndpoint = 'https://test.com/install';
        $shopId = Random::getAlphanumericString(12);

        $handshake = new PrivateHandshake($shopUrl, $secret, $appEndpoint, '', $shopId, Kernel::SHOPWARE_FALLBACK_VERSION);

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
            hash_hmac('sha256', $request->getUri()->getQuery(), $secret),
            $request->getHeaderLine('shopware-app-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
    }

    public function testAppProof(): void
    {
        $shopUrl = 'test.shop.com';
        $secret = 'stuff';
        $appEndpoint = 'https://test.com/install';
        $appName = 'testapp';
        $shopId = Random::getAlphanumericString(12);

        $handshake = new PrivateHandshake($shopUrl, $secret, $appEndpoint, $appName, $shopId, Kernel::SHOPWARE_FALLBACK_VERSION);

        $appProof = $handshake->fetchAppProof();

        static::assertEquals(hash_hmac('sha256', $shopId . $shopUrl . $appName, $secret), $appProof);
    }
}
