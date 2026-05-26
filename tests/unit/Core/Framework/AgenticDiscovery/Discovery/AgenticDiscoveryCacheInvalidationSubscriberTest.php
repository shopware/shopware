<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryCacheInvalidationSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryCacheInvalidationSubscriber::class)]
class AgenticDiscoveryCacheInvalidationSubscriberTest extends TestCase
{
    public function testSubscribesToEntityWrittenContainerEvent(): void
    {
        $events = AgenticDiscoveryCacheInvalidationSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
        static::assertSame('invalidateOnConfigWrite', $events[EntityWrittenContainerEvent::class]);
    }

    public function testDoesNothingWhenContainerEventCarriesNoTrackedWrites(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidate');

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([]),
            []
        );

        (new AgenticDiscoveryCacheInvalidationSubscriber($invalidator))->invalidateOnConfigWrite($event);
    }
}
