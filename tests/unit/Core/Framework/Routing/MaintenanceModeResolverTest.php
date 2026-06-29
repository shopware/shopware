<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MaintenanceModeResolver::class)]
class MaintenanceModeResolverTest extends TestCase
{
    public function testIsClientAllowedTriggersEventAndReturnsFalseForDisallowedClient(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class));

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertFalse($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), []));
    }

    public function testIsClientAllowedTriggersEventAndReturnsTrueForAllowedClient(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class));

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertTrue($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), ['192.168.0.4']));
    }

    public function testClientIsAllowedButEventDisallowsIt(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class))
            ->willReturnCallback(static function (MaintenanceModeRequestEvent $event) {
                static::assertTrue($event->isClientAllowed());
                $event->disallowClient();

                return $event;
            });

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertFalse($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), ['192.168.0.4']));
    }

    public function testClientIsDisallowedButEventAllowsIt(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class))
            ->willReturnCallback(static function (MaintenanceModeRequestEvent $event) {
                static::assertFalse($event->isClientAllowed());
                $event->allowClient();

                return $event;
            });

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertTrue($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), []));
    }

    public function testGetIpsFallsBackToDeprecatedAllowlistAttribute(): void
    {
        $resolver = new MaintenanceModeResolver($this->createMock(EventDispatcherInterface::class));

        $request = new Request(server: ['REMOTE_ADDR' => '192.168.0.4']);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, true);
        // only the deprecated attribute is provided
        $request->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST,
            json_encode(['192.168.0.4'], \JSON_THROW_ON_ERROR)
        );

        // the allowed client is read from the deprecated attribute, so it is not treated as a maintenance request
        static::assertFalse($resolver->isMaintenanceRequest($request));
    }

    public function testGetIpsPrefersTheNewAllowlistAttribute(): void
    {
        $resolver = new MaintenanceModeResolver($this->createMock(EventDispatcherInterface::class));

        $request = new Request(server: ['REMOTE_ADDR' => '192.168.0.4']);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, true);
        $request->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_ALLOWLIST,
            json_encode(['192.168.0.4'], \JSON_THROW_ON_ERROR)
        );

        static::assertFalse($resolver->isMaintenanceRequest($request));
    }
}
