<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
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
}
