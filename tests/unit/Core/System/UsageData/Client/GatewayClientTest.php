<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Client\GatewayClient;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(GatewayClient::class)]
class GatewayClientTest extends TestCase
{
    public function testGatewayAllowsPush(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            $gatewayKillSwitchOff = json_encode(['killswitch' => false], \JSON_THROW_ON_ERROR);

            return new MockResponse($gatewayKillSwitchOff);
        });

        $gatewayClient = new GatewayClient(
            $client,
            $this->createMock(ShopIdProvider::class),
            true
        );

        static::assertTrue($gatewayClient->isGatewayAllowsPush());
    }

    public function testGatewayDoesNotAllowPush(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            $gatewayKillSwitchOn = json_encode(['killswitch' => true], \JSON_THROW_ON_ERROR);

            return new MockResponse($gatewayKillSwitchOn);
        });

        $gatewayClient = new GatewayClient(
            $client,
            $this->createMock(ShopIdProvider::class),
            true
        );

        static::assertFalse($gatewayClient->isGatewayAllowsPush());
    }

    public function testGatewayDoesNotAllowPushInDevEnvironment(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            $gatewayKillSwitchOn = json_encode(['killswitch' => false], \JSON_THROW_ON_ERROR);

            return new MockResponse($gatewayKillSwitchOn);
        });

        $gatewayClient = new GatewayClient(
            $client,
            $this->createMock(ShopIdProvider::class),
            false
        );

        static::assertFalse($gatewayClient->isGatewayAllowsPush());
    }
}
