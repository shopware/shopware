<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Extension\ProductReviewRouteExtension;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Base64;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewRoute::class)]
class ProductReviewRouteTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<ProductReviewCollection>
     */
    private Stub&EntityRepository $repository;

    private StaticSystemConfigService $config;

    private CacheTagCollector&Stub $cacheTagCollector;

    private ProductReviewRoute $route;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->config = new StaticSystemConfigService([
            'test' => [
                'core.listing.showReview' => true,
                'core.listing.reviewsPerPage' => 10,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewPerPageAboveMaxLimit' => [
                'core.listing.showReview' => true,
                'core.listing.reviewsPerPage' => 150,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewNotActive' => [
                'core.listing.showReview' => false,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
        ]);

        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);

        $this->route = $this->createRoute();
    }

    public function testLoad(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->once())->method('getCustomer')->willReturn($customer);
        $salesChannelContext->expects($this->exactly(2))->method('getSalesChannelId')->willReturn('test');
        $salesChannelContext->expects($this->exactly(1))->method('getContext')->willReturn($context);

        $expectedCriteria = new Criteria();
        $expectedCriteria->setTitle('product-review-route');
        $expectedCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('status', true),
                    new EqualsFilter('customerId', $customer->getId()),
                ]),
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->with($expectedCriteria, $context);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with($this->route::buildName($productId));

        $this->createRoute($repository, $cacheTagCollector)->load(
            $productId,
            new Request(),
            $salesChannelContext,
            new Criteria(),
        );
    }

    public function testLoadAppliesConfiguredReviewsPerPageWhenRequestHasNoLimit(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('test');
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        // Simulates the criteria the RequestCriteriaBuilder produces without an explicit limit.
        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'product_review',
                    0,
                    new ProductReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $salesChannelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(10, $searchedCriteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $searchedCriteria->getTotalCountMode());
        static::assertFalse($searchedCriteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST));
    }

    public function testLoadRecomputesOffsetForConfiguredReviewsPerPage(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('test');
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        // Page 2 without an explicit limit: offset was derived from the max limit (100).
        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->setOffset(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'product_review',
                    0,
                    new ProductReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $salesChannelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(10, $searchedCriteria->getLimit());
        static::assertSame(10, $searchedCriteria->getOffset());
    }

    #[DataProvider('requestedCountModeProvider')]
    public function testLoadPreservesRequestedCountMode(string $requestType, int|string|null $countMode, int $expectedMode): void
    {
        $context = Generator::generateSalesChannelContext();
        $this->config->set('core.listing.showReview', true, $context->getSalesChannelId());
        $this->config->set('core.listing.reviewsPerPage', 10, $context->getSalesChannelId());

        $definition = new ProductReviewDefinition();
        $registry = new StaticDefinitionInstanceRegistry([$definition], Validation::createValidator(), new StaticEntityWriterGateway());
        $parser = new AggregationParser();
        $builder = new RequestCriteriaBuilder(
            $parser,
            new ApiCriteriaValidator($registry),
            new CriteriaArrayConverter($parser),
            new CompressedCriteriaDecoder(),
            100,
        );

        $payload = ['page' => 3];
        if ($countMode !== null) {
            $payload['total-count-mode'] = $countMode;
        }

        if ($requestType === 'compressed GET') {
            $compressed = gzencode(json_encode($payload, \JSON_THROW_ON_ERROR));
            static::assertNotFalse($compressed);
            $request = new Request([
                '_criteria' => Base64::urlEncode($compressed),
                'total-count-mode' => $countMode === null ? 'none' : 'exact',
            ]);
        } elseif ($requestType === Request::METHOD_POST) {
            $request = new Request(
                query: ['total-count-mode' => $countMode === null ? 'none' : 'exact'],
                request: $payload,
            );
            $request->setMethod(Request::METHOD_POST);
        } else {
            $request = new Request(
                query: $payload,
                request: ['total-count-mode' => $countMode === null ? 'none' : 'exact'],
            );
        }

        $criteria = $builder->handleRequest($request, new Criteria(), $definition, $context->getContext());
        $route = new ProductReviewRoute(
            new StaticEntityRepository([new ProductReviewCollection()]),
            $this->config,
            $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $result = $route->load(Uuid::randomHex(), $request, $context, $criteria)->getResult();

        static::assertSame(10, $result->getCriteria()->getLimit());
        static::assertSame(20, $result->getCriteria()->getOffset());
        static::assertSame($expectedMode, $result->getCriteria()->getTotalCountMode());
    }

    /**
     * @return iterable<string, array{string, int|string|null, int}>
     */
    public static function requestedCountModeProvider(): iterable
    {
        foreach ([Request::METHOD_GET, Request::METHOD_POST, 'compressed GET'] as $requestType) {
            yield $requestType . ' without count mode defaults to exact' => [$requestType, null, Criteria::TOTAL_COUNT_MODE_EXACT];
            yield $requestType . ' preserves disabled counting' => [$requestType, 'none', Criteria::TOTAL_COUNT_MODE_NONE];
            yield $requestType . ' preserves exact counting' => [$requestType, 'exact', Criteria::TOTAL_COUNT_MODE_EXACT];
            yield $requestType . ' preserves next-page counting' => [$requestType, 'next-pages', Criteria::TOTAL_COUNT_MODE_NEXT_PAGES];
            yield $requestType . ' preserves numeric disabled counting' => [$requestType, 0, Criteria::TOTAL_COUNT_MODE_NONE];
            yield $requestType . ' preserves numeric next-page counting' => [$requestType, 2, Criteria::TOTAL_COUNT_MODE_NEXT_PAGES];
        }
    }

    public function testLoadKeepsExplicitRequestLimit(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('test');
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        // Explicit limit in the request: no STATE_NO_EXPLICIT_LIMIT_IN_REQUEST state.
        $criteria = new Criteria();
        $criteria->setLimit(25);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'product_review',
                    0,
                    new ProductReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $salesChannelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(25, $searchedCriteria->getLimit());
        // An explicit request limit is left untouched, so the total count mode is not forced either.
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $searchedCriteria->getTotalCountMode());
    }

    public function testLoadCapsConfiguredReviewsPerPageToStoreApiMaxLimit(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('testReviewPerPageAboveMaxLimit');
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'product_review',
                    0,
                    new ProductReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $salesChannelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(100, $searchedCriteria->getLimit());
        static::assertFalse($searchedCriteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST));
    }

    public function testLoadReviewDeactivated(): void
    {
        $this->expectExceptionObject(ProductException::reviewNotActive());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->exactly(2))->method('getSalesChannelId')->willReturn('testReviewNotActive');

        $this->route->load(
            Uuid::randomHex(),
            new Request(),
            $salesChannelContext,
            new Criteria(),
        );
    }

    public function testPublishesExtension(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductReviewRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-review-route.load.pre', static function (ProductReviewRouteExtension $extension) use ($productId, $request, $context, $criteria, $response): void {
            static::assertSame(['productId' => $productId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductReviewRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($productId, $request, $context, $criteria));
    }

    public function testExtensionCanOverrideConfiguredPagination(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('test');
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->setOffset(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ProductReviewRouteExtension::onPre(), static function (ProductReviewRouteExtension $extension): void {
            static::assertSame(10, $extension->criteria->getLimit());
            static::assertSame(10, $extension->criteria->getOffset());
            static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $extension->criteria->getTotalCountMode());
            static::assertFalse($extension->criteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST));

            $extension->criteria->setLimit(5);
            $extension->criteria->setOffset(5);
        });

        $route = new ProductReviewRoute(
            new StaticEntityRepository([new ProductReviewCollection()]),
            $this->config,
            $this->cacheTagCollector,
            new ExtensionDispatcher($dispatcher),
        );

        $response = $route->load(Uuid::randomHex(), new Request(), $context, $criteria);

        static::assertSame(5, $response->getResult()->getCriteria()->getLimit());
        static::assertSame(5, $response->getResult()->getCriteria()->getOffset());
    }

    /**
     * @param (EntityRepository<ProductReviewCollection>&MockObject)|null $repository
     */
    private function createRoute(
        ?EntityRepository $repository = null,
        ?CacheTagCollector $cacheTagCollector = null,
    ): ProductReviewRoute {
        return new ProductReviewRoute(
            $repository ?? $this->repository,
            $this->config,
            $cacheTagCollector ?? $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
