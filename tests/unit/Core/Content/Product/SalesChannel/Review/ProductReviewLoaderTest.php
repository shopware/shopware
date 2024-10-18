<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductReviewLoader::class)]
class ProductReviewLoaderTest extends TestCase
{
    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $this->systemConfigService = new StaticSystemConfigService();
        $this->systemConfigService->set('core.listing.reviewsPerPage', 10, 'salesChannelId');
    }

    public function testItLoadsReviewsWithProductId(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRouteMock = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRouteMock);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $productReviewRouteMock
            ->method('load')
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->first());
        static::assertEquals($result->first()->getId(), $reviewId);
        static::assertCount(1, $result);
        static::assertNull($result->getCustomerReview());
    }

    public function testItLoadsReviewsPagination(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId, 'p' => 2]);
        $salesChannelContext = $this->getSalesChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRouteMock = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRouteMock);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $criteria = $this->createCriteria($request, $salesChannelContext);

        $productReviewRouteMock
            ->method('load')
            ->with($productId, $request, $salesChannelContext, $criteria)
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        $firstResult = $result->first();
        static::assertInstanceOf(ProductReviewEntity::class, $firstResult);
        static::assertEquals($firstResult->getId(), $reviewId);
        static::assertEquals($result->getCriteria()->getOffset(), 10);
        static::assertCount(1, $result);
        static::assertNull($result->getCustomerReview());
    }

    public function testNegativeOffsetDefaultsToZero(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId, 'p' => -2]);
        $salesChannelContext = $this->getSalesChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRouteMock = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRouteMock);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $criteria = $this->createCriteria($request, $salesChannelContext);

        $productReviewRouteMock
            ->method('load')
            ->with($productId, $request, $salesChannelContext, $criteria)
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->first());
        static::assertEquals($result->first()->getId(), $reviewId);
        static::assertEquals($result->getCriteria()->getOffset(), 0);
        static::assertCount(1, $result);
        static::assertNull($result->getCustomerReview());
    }

    public function testItLoadsReviewsWithParentId(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId, 'parentId' => $productId, 'sort' => 'points', 'language' => 'filter-language']);
        $salesChannelContext = $this->getSalesChannelContext();

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRouteMock = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRouteMock);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $productReviewRouteMock
            ->method('load')
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->first());
        static::assertEquals($reviewId, $result->first()->getId());
        static::assertCount(1, $result);
        static::assertEquals([new FieldSorting('points', 'DESC')], $result->getCriteria()->getSorting());
        static::assertNotNull($result->getCustomerReview());
    }

    public function testItLoadsReviewsWithPointsFilter(): void
    {
        $reviewId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId, 'points' => ['4', 'gg']]);
        $salesChannelContext = $this->getSalesChannelContext();

        $review = $this->getReviewEntity($reviewId);

        $reviews = new ProductReviewCollection([
            $review,
        ]);

        $productReviewRouteMock = $this->createMock(ProductReviewRoute::class);
        $productReviewLoader = $this->getProductReviewLoader($productReviewRouteMock);

        $reviewResult = $this->getDefaultResult($reviews, $request, $salesChannelContext);

        $productReviewRouteMock
            ->method('load')
            ->willReturn(
                new ProductReviewRouteResponse($reviewResult)
            );

        $result = $productReviewLoader->load($request, $salesChannelContext, $productId);

        static::assertInstanceOf(ProductReviewEntity::class, $result->first());
        static::assertEquals($result->first()->getId(), $reviewId);
        static::assertCount(1, $result);
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
        ProductReviewRoute $productReviewRouteMock
    ): ProductReviewLoader {
        return new ProductReviewLoader(
            $productReviewRouteMock,
            $this->systemConfigService,
            $this->createMock(EventDispatcherInterface::class)
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

        return new SalesChannelContext(
            Context::createDefaultContext(),
            'foo',
            'bar',
            $salesChannelEntity,
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            $customer,
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            []
        );
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $limit = (int) $request->get('limit', $this->systemConfigService->getInt('core.listing.reviewsPerPage', $context->getSalesChannelId()));
        $page = (int) $request->get('p', 1);
        $offset = max(0, $limit * ($page - 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sorting = new FieldSorting('createdAt', 'DESC');
        if ($request->get('sort', 'createdAt') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        if ($request->get('language') === 'filter-language') {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getContext()->getLanguageId())
            );
        } else {
            $criteria->addAssociation('language.translationCode.code');
        }

        $reviewFilters[] = new EqualsFilter('status', true);

        if ($context->getCustomer() !== null) {
            $reviewFilters[] = new EqualsFilter('customerId', $context->getCustomer()->getId());
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
                    new EqualsFilter('languageId', $context->getContext()->getLanguageId()),
                    new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                ]
            )
        );

        return $criteria;
    }
}
