<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader
 */
class ProductReviewLoaderTest extends TestCase
{
    private MockObject&AbstractProductReviewRoute $route;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private ProductReviewLoader $productReviewLoader;

    protected function setUp(): void
    {
        $this->route = $this->createMock(AbstractProductReviewRoute::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->productReviewLoader = new ProductReviewLoader(
            $this->route,
            $this->eventDispatcher
        );
    }

    public function testLoadThrowsParameterExceptionWhenProductIdMissing(): void
    {
        $this->expectException(ProductException::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->productReviewLoader->load(new Request(), $salesChannelContext);
    }

    public function testLoadWithoutCustomer(): void
    {
        $request = new Request();
        $productId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $request->request->add([
            'productId' => $productId,
            'parentId' => $parentId,
        ]);

        /** @var MockObject&SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $salesChannelContext->expects(static::exactly(2))->method('getCustomer')->willReturn(null);

        $review1 = $this->createReview();
        $review2 = $this->createReview();
        $reviewRouteResult = $this->createProductReviewRouteResponse([
            $review1->getId() => $review1,
            $review2->getId() => $review2,
        ], $this->createRatingPointsAggregation([
            5 => 1,
            1 => 1,
        ]));

        $this->route
            ->expects(static::once())
            ->method('load')
            ->with($parentId, static::isInstanceOf(Request::class), $salesChannelContext, static::isInstanceOf(Criteria::class))
            ->willReturn($reviewRouteResult)
        ;

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ProductReviewsLoadedEvent::class))
        ;

        $result = $this->productReviewLoader->load($request, $salesChannelContext);
        $matrix = $result->getMatrix();

        static::assertSame(6.0, $matrix->getPointSum());
        static::assertSame(2, $matrix->getTotalReviewCount());

        static::assertSame($productId, $result->getProductId());
        static::assertSame($parentId, $result->getParentId());
        static::assertNull($result->getCustomerReview());

        static::assertSame(2, $result->getTotal());
        static::assertCount(2, $result->getElements());
        static::assertArrayHasKey($review1->getId(), $result->getElements());
        static::assertArrayHasKey($review2->getId(), $result->getElements());
    }

    public function testLoadWithCustomerAndLanguageFilter(): void
    {
        $request = new Request();
        $productId = Uuid::randomHex();
        $request->request->add([
            'productId' => $productId,
            'language' => 'filter-language',
        ]);

        /** @var MockObject&SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');
        $customer->setEmail('foo@example.com');
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $salesChannelContext->expects(static::exactly(2))->method('getCustomer')->willReturn($customer);
        $salesChannelContext->expects(static::once())->method('getContext')->willReturn($context);

        $review = $this->createReview();
        $reviewRouteResult = $this->createProductReviewRouteResponse([
            $review->getId() => $review,
        ]);

        $customerReview = $this->createReview();
        $customerReviewRouteResult = $this->createProductReviewRouteResponse([
            $customerReview->getId() => $customerReview,
        ]);

        $this->route
            ->expects(static::exactly(2))
            ->method('load')
            ->with($productId, static::isInstanceOf(Request::class), $salesChannelContext, static::isInstanceOf(Criteria::class))
            ->will(static::onConsecutiveCalls($reviewRouteResult, $customerReviewRouteResult))
        ;

        $result = $this->productReviewLoader->load($request, $salesChannelContext);
        static::assertSame($productId, $result->getProductId());
        static::assertNull($result->getParentId());
        static::assertSame($customerReview, $result->getCustomerReview());

        static::assertSame(1, $result->getTotal());
        static::assertCount(1, $result->getElements());
        static::assertArrayHasKey($review->getId(), $result->getElements());
    }

    public function testCriteriaWithPointsAndInvalidSorting(): void
    {
        $request = new Request();
        $productId = Uuid::randomHex();
        $request->request->add([
            'productId' => $productId,
            'points' => [2, 3],
            'sort' => 'invalidSorting',
        ]);

        /** @var MockObject&SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $salesChannelContext->expects(static::exactly(2))->method('getCustomer')->willReturn(null);

        $review = $this->createReview();
        $reviewRouteResult = $this->createProductReviewRouteResponse([
            $review->getId() => $review,
        ]);

        $this->route
            ->expects(static::once())
            ->method('load')
            ->with(
                $productId,
                static::isInstanceOf(Request::class),
                $salesChannelContext,
                static::callback(function (Criteria $criteria) {
                    $postFilters = $criteria->getPostFilters();
                    static::assertCount(1, $postFilters);

                    $postFilter = reset($postFilters);
                    static::assertInstanceOf(MultiFilter::class, $postFilter);
                    static::assertCount(2, $postFilter->getFields());
                    static::assertContains('points', $postFilter->getFields());

                    /** @var MultiFilter $postFilter */
                    $queries = $postFilter->getQueries();
                    static::assertCount(2, $queries);

                    foreach ($queries as $query) {
                        static::assertInstanceOf(RangeFilter::class, $query);

                        /** @var RangeFilter $query */
                        static::assertTrue($query->hasParameter(RangeFilter::GTE));
                        static::assertTrue($query->hasParameter(RangeFilter::LT));

                        static::assertContains([
                            (float) $query->getParameter(RangeFilter::GTE),
                            (float) $query->getParameter(RangeFilter::LT),
                        ], [[1.5, 2.5], [2.5, 3.5]]);
                    }

                    $sortings = $criteria->getSorting();
                    static::assertCount(1, $sortings);

                    $sorting = reset($sortings);
                    static::assertInstanceOf(FieldSorting::class, $sorting);

                    static::assertSame('createdAt', $sorting->getField());
                    static::assertSame('DESC', $sorting->getDirection());

                    return true;
                })
            )
            ->willReturn($reviewRouteResult)
        ;

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ProductReviewsLoadedEvent::class))
        ;

        $result = $this->productReviewLoader->load($request, $salesChannelContext);
        static::assertSame($productId, $result->getProductId());
        static::assertNull($result->getParentId());
        static::assertNull($result->getCustomerReview());

        static::assertSame(1, $result->getTotal());
        static::assertCount(1, $result->getElements());
        static::assertArrayHasKey($review->getId(), $result->getElements());
    }

    public function testCriteriaRequestParameters(): void
    {
        $request = new Request();
        $productId = Uuid::randomHex();
        $request->request->add([
            'productId' => $productId,
            'limit' => 7,
            'p' => 3,
            'sort' => 'points',
        ]);

        /** @var MockObject&SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $salesChannelContext->expects(static::exactly(2))->method('getCustomer')->willReturn(null);

        $review = $this->createReview();
        $reviewRouteResult = $this->createProductReviewRouteResponse([
            $review->getId() => $review,
        ]);

        $this->route
            ->expects(static::once())
            ->method('load')
            ->with(
                $productId,
                static::isInstanceOf(Request::class),
                $salesChannelContext,
                static::callback(function (Criteria $criteria) {
                    $postFilters = $criteria->getPostFilters();
                    static::assertCount(0, $postFilters);

                    static::assertSame(7, $criteria->getLimit());
                    static::assertSame(14, $criteria->getOffset()); // (3 - 1) * 7

                    $sortings = $criteria->getSorting();
                    static::assertCount(1, $sortings);

                    $sorting = reset($sortings);
                    static::assertInstanceOf(FieldSorting::class, $sorting);

                    static::assertSame('points', $sorting->getField());
                    static::assertSame('DESC', $sorting->getDirection());

                    return true;
                })
            )
            ->willReturn($reviewRouteResult)
        ;

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ProductReviewsLoadedEvent::class))
        ;

        $result = $this->productReviewLoader->load($request, $salesChannelContext);
        static::assertSame($productId, $result->getProductId());
        static::assertNull($result->getParentId());
        static::assertNull($result->getCustomerReview());

        static::assertSame(1, $result->getTotal());
        static::assertCount(1, $result->getElements());
        static::assertArrayHasKey($review->getId(), $result->getElements());
    }

    private function createReview(): ProductReviewEntity
    {
        $review = new ProductReviewEntity();
        $review->setId(Uuid::randomHex());

        return $review;
    }

    /**
     * @param array<string, ProductReviewEntity> $reviews
     */
    private function createProductReviewRouteResponse(array $reviews, ?TermsResult $ratingMatrix = null): ProductReviewRouteResponse
    {
        $aggregationCollection = null;
        if ($ratingMatrix !== null) {
            $aggregationCollection = new AggregationResultCollection(['ratingMatrix' => $ratingMatrix]);
        }

        return new ProductReviewRouteResponse(new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            \count($reviews),
            new ProductReviewCollection($reviews),
            $aggregationCollection,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @param array<int, int> $points
     */
    private function createRatingPointsAggregation(array $points): TermsResult
    {
        $buckets = [];
        foreach ($points as $rating => $count) {
            $buckets[] = new Bucket((string) $rating, $count, null);
        }

        return new TermsResult('ratingMatrix', $buckets);
    }
}
