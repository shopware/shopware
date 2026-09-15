<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\SalesChannelCategoryIdsFetchedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelCategoryIdsFetchedEvent::class)]
class SalesChannelCategoryIdsFetchedEventTest extends TestCase
{
    public function testDeduplicatesTheFetchedIds(): void
    {
        $event = new SalesChannelCategoryIdsFetchedEvent(
            ['id-a', 'id-b', 'id-a'],
            static::createStub(SalesChannelContext::class),
        );

        static::assertSame(['id-a', 'id-b'], $event->getIds());
    }

    public function testFilterIdRemovesTheGivenId(): void
    {
        $event = new SalesChannelCategoryIdsFetchedEvent(
            ['id-a', 'id-b'],
            static::createStub(SalesChannelContext::class),
        );

        static::assertTrue($event->hasId('id-a'));

        $event->filterId('id-a');

        static::assertFalse($event->hasId('id-a'));
        static::assertSame(['id-b'], $event->getIds());
    }

    public function testExposesTheSalesChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $event = new SalesChannelCategoryIdsFetchedEvent(['id-a'], $salesChannelContext);

        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
