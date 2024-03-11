<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\HealthCheckController;
use Shopware\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
        );
        $response = $controller->check(Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertFalse($response->isCacheable());
    }

    public function testEventIsDispatched(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $controller = new HealthCheckController(
            $eventDispatcher,
        );
        $response = $controller->check(Context::createDefaultContext());

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(HealthCheckEvent::class, $eventDispatcher->getEvents()[0]);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
