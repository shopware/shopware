<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(MaintenanceModeRequestEvent::class)]
class MaintenanceModeRequestEventTest extends TestCase
{
    public function testIsClientAllowed(): void
    {
        $event = new MaintenanceModeRequestEvent(
            new Request(),
            [],
            true
        );

        static::assertTrue($event->isClientAllowed());

        $event->disallowClient();

        static::assertFalse($event->isClientAllowed());

        $event->allowClient();

        static::assertTrue($event->isClientAllowed());
    }

    public function testGetAllowedIps(): void
    {
        $event = new MaintenanceModeRequestEvent(
            new Request(),
            ['192.168.0.1'],
            true
        );

        static::assertEquals(['192.168.0.1'], $event->getAllowedIps());
    }

    public function testGetRequest(): void
    {
        $event = new MaintenanceModeRequestEvent(
            $request = new Request(),
            [],
            true
        );

        static::assertSame($request, $event->getRequest());
    }
}
