<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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

    public function testIgnoresOtherNestedEventsAndHandlesEmptyFilters(): void
    {
        $context = Context::createDefaultContext();
        $update = new EntityWriteResult('updated-product-id', ['active' => null], 'product', EntityWriteResult::OPERATION_UPDATE);
        $nestedEvents = new NestedEventCollection([
            new class($context) extends NestedEvent {
                public function __construct(private readonly Context $context)
                {
                }

                public function getContext(): Context
                {
                    return $this->context;
                }
            },
            new EntityWrittenEvent('product', [$update], $context),
        ]);

        /** @phpstan-ignore argument.type (Intentionally passes a non-written event to cover the defensive runtime guard.) */
        $event = new EntityWrittenContainerEvent($context, $nestedEvents, []);

        static::assertSame([$update], $event->getResults('product')->getElements());
        static::assertSame(['updated-product-id'], $event->getPrimaryKeysWithPropertyChange('product', ['active']));
        static::assertSame([], $event->getPrimaryKeysWithPropertyChange('product', []));
        static::assertSame([], $event->getDeletedPrimaryKeys('product'));
        static::assertSame([], $event->getPrimaryKeys('missing'));
    }

    public function testExposesContainerStateAndFindsEvents(): void
    {
        $context = Context::createDefaultContext();
        $productResult = new EntityWriteResult('product-id', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $categoryResult = new EntityWriteResult('category-id', [], 'category', EntityWriteResult::OPERATION_INSERT);
        $productEvent = new EntityWrittenEvent('product', [$productResult], $context);
        $container = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([$productEvent]),
            ['write-error'],
        );

        static::assertSame($context, $container->getContext());
        static::assertSame(['write-error'], $container->getErrors());
        static::assertSame($productEvent, $container->getEventByEntityName('product'));
        static::assertNull($container->getEventByEntityName('category'));
        static::assertSame(['product.written' => ['product-id']], $container->getList());
        static::assertFalse($container->isCloned());

        $categoryEvent = new EntityWrittenEvent('category', [$categoryResult], $context);
        $container->addEvent($categoryEvent);
        $container->setCloned(true);

        static::assertSame($categoryEvent, $container->getEventByEntityName('category'));
        static::assertSame(
            ['product.written' => ['product-id'], 'category.written' => ['category-id']],
            $container->getList(),
        );
        static::assertTrue($container->isCloned());
    }

    public function testCreatesWrittenAndDeletedEvents(): void
    {
        $context = Context::createDefaultContext();
        $insert = new EntityWriteResult('insert-id', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $delete = new EntityWriteResult('delete-id', [], 'category', EntityWriteResult::OPERATION_DELETE);

        $written = EntityWrittenContainerEvent::createWithWrittenEvents(
            ['product' => [$insert], 'empty' => []],
            $context,
            ['write-error'],
            true,
        );
        $deleted = EntityWrittenContainerEvent::createWithDeletedEvents(['category' => [$delete]], $context, []);

        static::assertSame([$insert], $written->getResults('product')->getElements());
        static::assertSame(['write-error'], $written->getErrors());
        static::assertTrue($written->isCloned());
        static::assertInstanceOf(EntityDeletedEvent::class, $deleted->getEventByEntityName('category'));
        static::assertSame(['delete-id'], $deleted->getDeletedPrimaryKeys('category'));
    }

    public function testReturnsPrimaryKeysWithPayloadIgnoringFields(): void
    {
        $context = Context::createDefaultContext();
        $delete = new EntityWriteResult('delete-id', [], 'product', EntityWriteResult::OPERATION_DELETE);
        $ignoredUpdate = new EntityWriteResult('ignored-id', ['updatedAt' => 'date'], 'product', EntityWriteResult::OPERATION_UPDATE);
        $changedUpdate = new EntityWriteResult(
            'changed-id',
            ['updatedAt' => 'date', 'active' => true],
            'product',
            EntityWriteResult::OPERATION_UPDATE,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('product', [$delete, $ignoredUpdate, $changedUpdate], $context)]),
            [],
        );

        static::assertSame(
            ['delete-id', 'changed-id'],
            $event->getPrimaryKeysWithPayloadIgnoringFields('product', ['updatedAt']),
        );
    }
}
