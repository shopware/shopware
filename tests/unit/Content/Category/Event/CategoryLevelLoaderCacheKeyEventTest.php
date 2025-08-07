<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Content\Category\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(CategoryLevelLoaderCacheKeyEvent::class)]
#[Package('discovery')]
class CategoryLevelLoaderCacheKeyEventTest extends TestCase
{
    public function testEvent(): void
    {
        // Prepare test data
        $initialParts = ['part1', 'part2'];
        $rootId = 'root-id';
        $depth = 3;
        $salesChannelId = 'sales-channel-id';
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);
        $criteria = new Criteria();

        $event = new CategoryLevelLoaderCacheKeyEvent(
            $initialParts,
            $rootId,
            $depth,
            $context,
            $criteria
        );

        static::assertSame($initialParts, $event->getParts());
        static::assertSame($context, $event->getContext());
        static::assertSame($rootId, $event->getRootId());
        static::assertSame($depth, $event->getDepth());
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($salesChannelId, $event->getSalesChannelId());
        static::assertTrue($event->shouldCache());

        $newParts = ['new-part1', 'new-part2'];
        $event->setParts($newParts);
        static::assertSame($newParts, $event->getParts());

        $additionalPart = 'new-part3';
        $event->addPart($additionalPart);
        static::assertSame([...$newParts, $additionalPart], $event->getParts());

        $event->disableCaching();
        static::assertFalse($event->shouldCache());
    }
}
