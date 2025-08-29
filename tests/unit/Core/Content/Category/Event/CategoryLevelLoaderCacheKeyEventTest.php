<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Framework\Context;
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
        $defaultContext = Context::createDefaultContext();
        // Prepare test data
        $initialParts = ['part1' => 'test', 'part2' => 'test2'];
        $rootId = 'root-id';
        $depth = 3;
        $salesChannelId = 'sales-channel-id';
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);
        $context->method('getContext')->willReturn($defaultContext);

        $criteria = new Criteria();

        $event = new CategoryLevelLoaderCacheKeyEvent(
            $initialParts,
            $rootId,
            $depth,
            $context,
            $criteria
        );

        static::assertSame($initialParts, $event->getParts());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertTrue($event->shouldCache());

        $newParts = ['new-part1' => 'test', 'new-part2' => 'test2'];
        $event->setParts($newParts);
        static::assertSame($newParts, $event->getParts());

        $event->addPart('new-part3', 'test3');
        static::assertSame(array_merge($newParts, ['new-part3' => 'test3']), $event->getParts());

        $event->removePart('new-part3');
        static::assertSame($newParts, $event->getParts());

        $event->disableCaching();
        static::assertFalse($event->shouldCache());
    }
}
