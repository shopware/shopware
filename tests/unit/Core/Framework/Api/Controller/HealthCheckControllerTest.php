<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\HealthCheckController;
use Shopware\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(HealthCheckController::class)]
class HealthCheckControllerTest extends TestCase
{
    public function testCheck(): void
    {
        $controller = new HealthCheckController(
            $this->createMock(EventDispatcher::class),
            $this->createMock(SystemChecker::class),
        );
        $response = $controller->check(Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertFalse($response->isCacheable());
    }

    public function testSystemHealthCheck(): void
    {
        $systemChecker = $this->createMock(SystemChecker::class);
        $controller = new HealthCheckController(
            $this->createMock(EventDispatcher::class),
            $systemChecker,
        );

        $extra = [
            'storeFrontUrl' => 'http://localhost/',
            'responseCode' => 200,
            'responseTime' => 0.07630205154418945,
        ];

        $result = new Result('SaleChannelReadiness', Status::OK, 'All sales channels are OK', true, $extra);
        $systemChecker->expects(static::once())
            ->method('check')
            ->willReturn([$result]);

        $response = $controller->health(Request::create('', 'GET', ['verbose' => 'true']));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $expectedResponse = [
            'checks' => [
                [
                    'name' => 'SaleChannelReadiness',
                    'healthy' => true,
                    'status' => 'OK',
                    'message' => 'All sales channels are OK',
                    'extra' => $extra,
                ],
            ],
        ];
        static::assertIsString($response->getContent());
        static::assertIsString(json_encode($expectedResponse));
        static::assertJsonStringEqualsJsonString(json_encode($expectedResponse), $response->getContent());
    }

    public function testEventIsDispatched(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $controller = new HealthCheckController(
            $eventDispatcher,
            $this->createMock(SystemChecker::class),
        );
        $response = $controller->check(Context::createDefaultContext());

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(HealthCheckEvent::class, $eventDispatcher->getEvents()[0]);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
