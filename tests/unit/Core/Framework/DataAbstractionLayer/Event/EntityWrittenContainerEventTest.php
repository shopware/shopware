<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

/**
 * @internal
 */
#[CoversClass(EntityWrittenContainerEvent::class)]
class EntityWrittenContainerEventTest extends TestCase
{
    public function testReturnsWriteResultsForEntity(): void
    {
        $context = Context::createDefaultContext();
        $writeResult = new EntityWriteResult('product-id', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('product', [$writeResult], $context)]),
            [],
        );

        static::assertSame([$writeResult], $event->getResults('product')->getElements());
        static::assertTrue($event->getResults('category')->isEmpty());
    }

    public function testAggregatesWriteResultsFromMultipleEventsForEntity(): void
    {
        $context = Context::createDefaultContext();
        $delete = new EntityWriteResult('deleted-product-id', [], 'product', EntityWriteResult::OPERATION_DELETE);
        $update = new EntityWriteResult('updated-product-id', ['childCount' => 1], 'product', EntityWriteResult::OPERATION_UPDATE);
        $category = new EntityWriteResult('category-id', [], 'category', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityDeletedEvent('product', [$delete], $context),
                new EntityWrittenEvent('category', [$category], $context),
                new EntityWrittenEvent('product', [$update], $context),
            ]),
            [],
        );

        static::assertSame([$delete, $update], $event->getResults('product')->getElements());
        static::assertSame(['deleted-product-id', 'updated-product-id'], $event->getPrimaryKeys('product'));
        static::assertSame(['deleted-product-id'], $event->getDeletedPrimaryKeys('product'));
        static::assertSame(['updated-product-id'], $event->getPrimaryKeysWithPropertyChange('product', ['childCount']));
    }
}
