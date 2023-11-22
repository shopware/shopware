<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\EntityIndexingSubscriber;
use Shopware\Core\Framework\Event\NestedEventCollection;

/**
 * @internal
 */
#[CoversClass(EntityIndexingSubscriber::class)]
class EntityIndexingSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertArrayHasKey(EntityWrittenContainerEvent::class, EntityIndexingSubscriber::getSubscribedEvents());
    }

    public function testRefresh(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects(static::once())->method('refresh');

        $subscriber = new EntityIndexingSubscriber($registry);
        $subscriber->refreshIndex(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));
    }
}
