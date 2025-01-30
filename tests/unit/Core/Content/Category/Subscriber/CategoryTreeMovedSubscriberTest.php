<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Subscriber\CategoryTreeMovedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[CoversClass(CategoryTreeMovedSubscriber::class)]
class CategoryTreeMovedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = CategoryTreeMovedSubscriber::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertArrayHasKey('Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent', $events);
        static::assertSame('detectSalesChannelEntryPoints', $events['Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent']);
    }

    public function testNotRootChange(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects(static::never())->method('sendIndexingMessage');
        $subscriber = new CategoryTreeMovedSubscriber($registry);

        $event = new EntityWrittenContainerEvent(Context::createCLIContext(), new NestedEventCollection(), []);
        $subscriber->detectSalesChannelEntryPoints($event);
    }

    public function testDetectSalesChannelEntryPoints(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects(static::once())->method('sendIndexingMessage')->with(['category.indexer', 'product.indexer']);
        $subscriber = new CategoryTreeMovedSubscriber($registry);

        $event = new EntityWrittenEvent(
            SalesChannelDefinition::ENTITY_NAME,
            [
                new EntityWriteResult('test', ['navigationCategoryId' => 'asd'], SalesChannelDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
            ],
            Context::createCLIContext()
        );

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $subscriber->detectSalesChannelEntryPoints($event);
    }
}
