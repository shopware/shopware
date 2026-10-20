<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCollectSortingEvent;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingCollectSortingEvent::class)]
class ProductListingCollectSortingEventTest extends TestCase
{
    public function testExposesItsPayload(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $request = new Request();
        $sortings = new ProductSortingCollection();

        $event = new ProductListingCollectSortingEvent($request, $sortings, $salesChannelContext);

        static::assertSame($request, $event->getRequest());
        static::assertSame($sortings, $event->getSortings());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
