<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewLoader::class)]
class ProductReviewLoaderTest extends TestCase
{
    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->systemConfigService->set('core.listing.reviewsPerPage', 10, 'salesChannelId');
    }

    public function testItLoadsReviewsWithProductId(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request();
        $salesChannelContext = $this->getSalesChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRoute = static::createStub(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $productReviewRoute
            ->method('load')
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->getEntities()->first());
        static::assertSame($result->getEntities()->first()->getId(), $reviewId);
        static::assertCount(1, $result->getEntities());
        static::assertNull($result->getCustomerReview());
    }

    public function testItLoadsReviewsPagination(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request(['p' => 2]);
        $salesChannelContext = $this->getSalesChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRoute = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $criteria = $this->createCriteria($request, $salesChannelContext);

        $productReviewRoute->expects($this->once())->method('load')->willReturnCallback(
            static function (string $actualProductId, Request $actualRequest, SalesChannelContext $actualContext, Criteria $actualCriteria) use ($productId, $request, $salesChannelContext, $criteria, $reviewResult): ProductReviewRouteResponse {
                static::assertSame($productId, $actualProductId);
                static::assertSame($request, $actualRequest);
                static::assertSame($salesChannelContext, $actualContext);
                static::assertEquals($criteria, $actualCriteria);

                return new ProductReviewRouteResponse($reviewResult);
            }
        );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        $firstResult = $result->getEntities()->first();
        static::assertInstanceOf(ProductReviewEntity::class, $firstResult);
        static::assertSame($firstResult->getId(), $reviewId);
        static::assertSame($result->getCriteria()->getOffset(), 10);
        static::assertCount(1, $result->getEntities());
        static::assertNull($result->getCustomerReview());
    }

    public function testNegativeOffsetDefaultsToZero(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request(['p' => -2]);
        $salesChannelContext = $this->getSalesChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRoute = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $criteria = $this->createCriteria($request, $salesChannelContext);

        $productReviewRoute->expects($this->once())->method('load')->willReturnCallback(
            static function (string $actualProductId, Request $actualRequest, SalesChannelContext $actualContext, Criteria $actualCriteria) use ($productId, $request, $salesChannelContext, $criteria, $reviewResult): ProductReviewRouteResponse {
                static::assertSame($productId, $actualProductId);
                static::assertSame($request, $actualRequest);
                static::assertSame($salesChannelContext, $actualContext);
                static::assertEquals($criteria, $actualCriteria);

                return new ProductReviewRouteResponse($reviewResult);
            }
        );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->getEntities()->first());
        static::assertSame($result->getEntities()->first()->getId(), $reviewId);
        static::assertSame($result->getCriteria()->getOffset(), 0);
        static::assertCount(1, $result->getEntities());
        static::assertNull($result->getCustomerReview());
    }

    public function testItLoadsReviewsWithParentId(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request(['sort' => 'points', 'language' => 'filter-language']);
        $salesChannelContext = $this->getSalesChannelContext();

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRoute = static::createStub(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $productReviewRoute
            ->method('load')
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->getEntities()->first());
        static::assertSame($reviewId, $result->getEntities()->first()->getId());
        static::assertCount(1, $result->getEntities());
        static::assertEquals([new FieldSorting('points', 'DESC')], $result->getCriteria()->getSorting());
        static::assertNotNull($result->getCustomerReview());
    }

    public function testItLoadsReviewsWithPointsFilter(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request(['points' => ['4', 'gg']]);
        $salesChannelContext = $this->getSalesChannelContext();

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRoute = static::createStub(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $productReviewRoute
            ->method('load')
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->getEntities()->first());
        static::assertSame($result->getEntities()->first()->getId(), $reviewId);
        static::assertCount(1, $result->getEntities());
    }

    private function getReviewEntity(string $reviewId): ProductReviewEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $review = new ProductReviewEntity();
        $review->setId($reviewId);
        $review->setUniqueIdentifier($reviewId);
        $review->setCustomer($customer);

        return $review;
    }

    private function getProductReviewLoader(
        ProductReviewRoute $productReviewRoute
    ): ProductReviewLoader {
        return new ProductReviewLoader(
            $productReviewRoute,
            $this->systemConfigService,
            static::createStub(EventDispatcherInterface::class)
        );
    }

    /**
     * @return EntitySearchResult<ProductReviewCollection>
     */
    private function getDefaultResult(
        ProductReviewCollection $reviews,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): EntitySearchResult {
        $criteria = $this->createCriteria($request, $salesChannelContext);

        return new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            1,
            $reviews,
            new AggregationResultCollection(
                [
                    'ratingMatrix' => new TermsResult('ratingMatrix', []),
                ],
            ),
            $criteria,
            Context::createDefaultContext()
        );
    }

    private function getSalesChannelContext(bool $setCustomer = true): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        $customer = null;

        if ($setCustomer) {
            $customer = new CustomerEntity();
            $customer->setId(Uuid::randomHex());
        }

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
            overrides: ['customer' => $customer],
        );
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $limit = $this->systemConfigService->getInt('core.listing.reviewsPerPage', $context->getSalesChannelId());
        $page = $request->query->getInt('p', 1);
        $offset = max(0, $limit * ($page - 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sorting = new FieldSorting('createdAt', 'DESC');
        if ($request->query->get('sort', 'createdAt') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        if ($request->query->get('language') === 'filter-language') {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );
        } else {
            $criteria->addAssociation('language.translationCode.code');
        }

        $reviewFilters = [new EqualsFilter('status', true)];

        if ($context->getCustomer() !== null) {
            $reviewFilters[] = new EqualsFilter('customerId', $context->getCustomerId());
        }

        $criteria->addAggregation(
            new FilterAggregation(
                'customer-login-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [
                    new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                ]
            ),
            new FilterAggregation(
                'language-filter',
                new TermsAggregation('languageMatrix', 'languageId'),
                [
                    new EqualsFilter('languageId', $context->getLanguageId()),
                    new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                ]
            )
        );

        return $criteria;
    }
}
