<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Client\GatewayClient;
use Shopware\Core\System\UsageData\Services\GatewayStatusService;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(GatewayStatusService::class)]
class GatewayStatusServiceTest extends TestCase
{
    public function testGatewayAllowsPush(): void
    {
        $gatewayClient = $this->createMock(GatewayClient::class);
        $gatewayClient->method('isGatewayAllowsPush')
            ->willReturn(true);

        $gatewayStatusService = new GatewayStatusService($gatewayClient);

        static::assertTrue($gatewayStatusService->isGatewayAllowsPush());
    }

    public function testGatewayDoesNotAllowPush(): void
    {
        $gatewayClient = $this->createMock(GatewayClient::class);
        $gatewayClient->method('isGatewayAllowsPush')
            ->willReturn(false);

        $gatewayStatusService = new GatewayStatusService($gatewayClient);

        static::assertFalse($gatewayStatusService->isGatewayAllowsPush());
    }

    public function testGatewayDoesNotAllowPushIfClientThrowsServerException(): void
    {
        $gatewayClient = $this->createMock(GatewayClient::class);
        $gatewayClient->method('isGatewayAllowsPush')
            ->willThrowException(new ServerException(
                new MockResponse('', ['http_code' => Response::HTTP_SERVICE_UNAVAILABLE])
            ));

        $gatewayStatusService = new GatewayStatusService($gatewayClient);

        static::assertFalse($gatewayStatusService->isGatewayAllowsPush());
    }
}
