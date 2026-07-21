<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityWrittenEvent::class)]
class EntityWrittenEventTest extends TestCase
{
    public function testReturnsWriteResultsAsCollection(): void
    {
        $insert = new EntityWriteResult('insert-id', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', [], 'product', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('product', [$insert, $update], Context::createDefaultContext());

        static::assertSame([$insert, $update], $event->getResults()->getElements());
        static::assertSame([1 => $update], $event->getResults()->only(EntityWriteResult::OPERATION_UPDATE)->getElements());
        static::assertSame([$insert, $update], $event->getWriteResults());
    }

    public function testReturnsEmptyCollectionWithoutWriteResults(): void
    {
        $event = new EntityWrittenEvent('product', [], Context::createDefaultContext());

        static::assertTrue($event->getResults()->isEmpty());
    }

    public function testExposesEventData(): void
    {
        $context = Context::createDefaultContext();
        $insert = new EntityWriteResult('insert-id', ['name' => 'Example'], 'product', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', ['active' => false], 'product', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('product', [$insert, $update], $context, ['write-error']);

        static::assertSame('product.written', $event->getName());
        static::assertSame('product', $event->getEntityName());
        static::assertSame($context, $event->getContext());
        static::assertSame(['write-error'], $event->getErrors());
        static::assertSame(['insert-id', 'update-id'], $event->getIds());
        static::assertSame(['insert-id', 'update-id'], $event->getIds());
        static::assertSame([['name' => 'Example'], ['active' => false]], $event->getPayloads());
        static::assertSame([['name' => 'Example'], ['active' => false]], $event->getPayloads());
    }
}
