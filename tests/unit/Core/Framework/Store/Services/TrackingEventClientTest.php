<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\TrackingEventClient;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TrackingEventClient::class)]
class TrackingEventClientTest extends TestCase
{
    public function testEventRequestNotMadeIfInstanceIdIsUnknown(): void
    {
        $mockHandler = new MockHandler();
        $mockHandler->append(new Response(200));

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->method('getShopwareVersion')->willReturn('6.5.0.0-test');
        $instanceService->method('getInstanceId')->willReturn(null);

        $trackingEventClient = new TrackingEventClient(
            new Client(['handler' => HandlerStack::create($mockHandler)]),
            $instanceService
        );

        $trackingEventClient->fireTrackingEvent('Example event name');
        static::assertNull($mockHandler->getLastRequest());

        // an exception would be thrown if a request was made
    }

    public function testTrackingEventFired(): void
    {
        $mockHandler = new MockHandler();
        $mockHandler->append(new Response(200));
        $httpClient = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->method('getShopwareVersion')->willReturn('6.5.0.0-test');
        $instanceService->method('getInstanceId')->willReturn('test-instance-id');

        $trackingEventClient = new TrackingEventClient($httpClient, $instanceService);
        $trackingEventClient->fireTrackingEvent('Tracking event fired and returned', [
            'someAdditionalData' => 'xy',
        ]);

        $lastRequest = $mockHandler->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => 'test-instance-id',
                'additionalData' => [
                    'shopwareVersion' => '6.5.0.0-test',
                    'someAdditionalData' => 'xy',
                ],
                'event' => 'Tracking event fired and returned',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testEventDoesNotThrowExceptionOnRequestException(): void
    {
        $mockHandler = new MockHandler();
        $mockHandler->append(new \Exception());
        $httpClient = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->method('getShopwareVersion')->willReturn('6.5.0.0-test');
        $instanceService->method('getInstanceId')->willReturn('test-instance-id');

        $trackingEventClient = new TrackingEventClient($httpClient, $instanceService);
        $trackingEventClient->fireTrackingEvent('Tracking event fired and returned on request exception', [
            'someAdditionalData' => 'xy',
        ]);

        $lastRequest = $mockHandler->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => 'test-instance-id',
                'additionalData' => [
                    'shopwareVersion' => '6.5.0.0-test',
                    'someAdditionalData' => 'xy',
                ],
                'event' => 'Tracking event fired and returned on request exception',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }
}
