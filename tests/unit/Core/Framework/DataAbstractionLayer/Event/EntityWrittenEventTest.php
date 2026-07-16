<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * @internal
 */
#[CoversClass(EntityWrittenEvent::class)]
class EntityWrittenEventTest extends TestCase
{
    public function testReturnsWriteResultsAsCollection(): void
    {
        $writeResult = new EntityWriteResult('product-id', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $event = new EntityWrittenEvent('product', [$writeResult], Context::createDefaultContext());

        static::assertSame([$writeResult], $event->getResults()->getElements());
    }
}
