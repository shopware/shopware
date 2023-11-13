<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Client;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Client\GatewayClient;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Client\GatewayClient
 */
#[Package('merchant-services')]
class GatewayClientTest extends TestCase
{
    public function testGatewayAllowsPush(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            $gatewayKillSwitchOff = json_encode(['killswitch' => false]);
            static::assertIsString($gatewayKillSwitchOff);

            return new MockResponse($gatewayKillSwitchOff);
        });

        $gatewayClient = new GatewayClient(
            $client,
            $this->createMock(ShopIdProvider::class),
        );

        static::assertTrue($gatewayClient->isGatewayAllowsPush());
    }

    public function testGatewayDoesNotAllowPush(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            $gatewayKillSwitchOn = json_encode(['killswitch' => true]);
            static::assertIsString($gatewayKillSwitchOn);

            return new MockResponse($gatewayKillSwitchOn);
        });

        $gatewayClient = new GatewayClient(
            $client,
            $this->createMock(ShopIdProvider::class),
        );

        static::assertFalse($gatewayClient->isGatewayAllowsPush());
    }
}
