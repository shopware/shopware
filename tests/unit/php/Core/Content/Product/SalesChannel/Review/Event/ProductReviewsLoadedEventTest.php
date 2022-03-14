<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent
 */
class ProductReviewsLoadedEventTest extends TestCase
{
    public function testInstance(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $productReviewLoaderResult = new ProductReviewLoaderResult(
            ProductReviewDefinition::ENTITY_NAME,
            42,
            new ProductReviewCollection(),
            null,
            new Criteria(),
            $context
        );

        $request = new Request();

        $event = new ProductReviewsLoadedEvent(
            $productReviewLoaderResult,
            $salesChannelContext,
            $request
        );

        static::assertSame($productReviewLoaderResult, $event->getSearchResult());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
        static::assertSame($request, $event->getRequest());
    }
}
