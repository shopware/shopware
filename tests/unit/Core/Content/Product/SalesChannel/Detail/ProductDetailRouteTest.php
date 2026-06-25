<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Detail\Event\ResolveVariantIdEvent;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductDetailRoute::class)]
class ProductDetailRouteTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private SalesChannelRepository $productRepository;

    /**
     * @var MockObject&SystemConfigService
     */
    private SystemConfigService $systemConfig;

    /**
     * @var MockObject&EntityRepository<ProductTranslationCollection>
     */
    private MockObject&EntityRepository $productTranslationRepository;

    private MockObject&Connection $connection;

    private ProductDetailRoute $route;

    private SalesChannelContext $context;

    private IdsCollection $idsCollection;

    private MockObject&CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private MockObject&SalesChannelCmsPageLoader $cmsPageLoader;

    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Generator::generateSalesChannelContext();
        $this->idsCollection = new IdsCollection();
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->productTranslationRepository = $this->createMock(EntityRepository::class);
        $this->systemConfig = $this->createMock(SystemConfigService::class);
        $this->connection = $this->createMock(Connection::class);
        $configuratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $this->breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $this->cmsPageLoader = $this->createMock(SalesChannelCmsPageLoader::class);
        $this->productCloseoutFilterFactory = new ProductCloseoutFilterFactory();
        $this->eventDispatcher = new EventDispatcher();
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $this->route = new ProductDetailRoute(
            $this->productRepository,
            $this->productTranslationRepository,
            $this->systemConfig,
            $this->connection,
            $configuratorLoader,
            $this->breadcrumbBuilder,
            $this->cmsPageLoader,
            $this->createMock(EntityCmsSlotConfigInheritanceBuilder::class),
            new SalesChannelProductDefinition(),
            $this->productCloseoutFilterFactory,
            $this->eventDispatcher,
            $cacheTagCollector,
        );
    }

    public function testLoadMainVariant(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');
        $this->productRepository->expects($this->exactly(1))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $result = $this->route->load('1', new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadBestVariant(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setCmsPageId('4');
        $productEntity->setId($this->idsCollection->create('product1'));
        $productEntity->setAvailable(true);
        $productEntity->setUniqueIdentifier('BestVariant');

        $product1Id = $this->idsCollection->create('product1');
        $idsSearchResult = new IdSearchResult(
            1,
            [
                $product1Id => [
                    'primaryKey' => $product1Id,
                    'data' => [],
                ],
            ],
            new Criteria(),
            $this->context->getContext()
        );
        $this->productRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 4, new ProductCollection([$productEntity]), null, new Criteria(), $this->context->getContext())
            );

        $result = $this->route->load($product1Id, new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('BestVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testLoadParentSearchUsesMatchedVariantWhenFindBestVariantEnabled(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => null,
                'parentId' => null,
            ]);

        $this->systemConfig->method('getBool')
            ->willReturnCallback(static fn (string $key, ?string $_salesChannelId = null): bool => $key === 'core.listing.findBestVariant');

        $productTerm = new SalesChannelProductEntity();
        $productTerm->setCmsPageId('term');
        $productTerm->setId($this->idsCollection->create('term'));
        $productTerm->setUniqueIdentifier('term');
        $productTerm->setName('term');

        $product1Id = $this->idsCollection->create('product1');
        $idsSearchResult = new IdSearchResult(
            1,
            [
                $product1Id => [
                    'primaryKey' => $product1Id,
                    'data' => [],
                ],
            ],
            new Criteria(),
            $this->context->getContext()
        );
        $this->productRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($product1Id): void {
            static::assertSame($product1Id, $event->getResolvedVariantId());
        });

        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 4, new ProductCollection([$productTerm]), null, new Criteria(), $this->context->getContext())
            );
        $request = new Request();
        $request->query->set('search', 'term');

        $result = $this->route->load($product1Id, $request, $this->context, new Criteria());

        static::assertSame('term', $result->getProduct()->getCmsPageId());
        static::assertSame('term', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadParentSearchKeepsMainVariantWhenFindBestVariantDisabled(): void
    {
        $mainVariantId = Uuid::randomHex();
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": false, "mainVariantId": "' . $mainVariantId . '"}',
                'parentId' => null,
            ]);

        $this->systemConfig->method('getBool')->willReturn(false);

        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId($mainVariantId);
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($mainVariantId): void {
            static::assertSame($mainVariantId, $event->getResolvedVariantId());
        });

        $this->productRepository->expects($this->never())
            ->method('searchIds');
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $request = new Request();
        $request->query->set('search', 'term');

        $result = $this->route->load(Uuid::randomHex(), $request, $this->context, new Criteria());

        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadVariantListingConfig(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": false, "mainVariantId": "2"}',
                'parentId' => '2',
            ]);

        $productId = Uuid::randomHex();
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId($productId);
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('2');
        $productEntity->setAvailable(true);
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($productId): void {
            static::assertSame($productId, $event->getProductId());
            static::assertSame('2', $event->getResolvedVariantId());
        });

        $result = $this->route->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testResolveVariantIdFromEvent(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": true, "mainVariantId": "2"}',
                'parentId' => '2',
            ]);

        $variantId = Uuid::randomHex();
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId($variantId);
        $productEntity->setCmsPageId('4');
        $productEntity->setAvailable(true);
        $this->productRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (Criteria $criteria) use ($variantId): bool {
                $ids = $criteria->getIds();
                static::assertCount(1, $ids);
                static::assertSame($variantId, reset($ids));

                return true;
            }))
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($variantId): void {
            $event->setResolvedVariantId($variantId);
        });

        $result = $this->route->load(Uuid::randomHex(), new Request(), $this->context, new Criteria());

        static::assertSame($variantId, $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testResolveVariantIdFromEventWithWrongTypeForDisplayParent(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": 1, "mainVariantId": null}', // Wrong displayParent type, should be boolean
                'parentId' => '2',
            ]);

        $productId = Uuid::randomHex();
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId($productId);
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('2');
        $productEntity->setAvailable(true);
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($productId): void {
            static::assertSame($productId, $event->getProductId());
            // In checkVariantListingConfig we want to make sure that the variant ID is not returned against displayParent when no variant ID is set
            static::assertNull($event->getResolvedVariantId(), 'Wrong variant ID resolved:' . $event->getResolvedVariantId());
        });

        $result = $this->route->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testResolveVariantIdFromEventWithDisplayParent(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": 1, "mainVariantId": "2"}',
                'parentId' => '2',
            ]);

        $productId = Uuid::randomHex();
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId($productId);
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('2');
        $productEntity->setAvailable(true);
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($productId): void {
            static::assertSame($productId, $event->getProductId());
            // In checkVariantListingConfig we want to make sure that the variant ID is returned even if displayParent is true
            static::assertSame('2', $event->getResolvedVariantId(), 'Wrong variant ID resolved:' . $event->getResolvedVariantId());
        });

        $result = $this->route->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testConfigHideCloseoutProductsWhenOutOfStockFiltersResults(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('BestVariant');

        $criteria2 = new Criteria([$this->idsCollection->get('product2')]);
        $criteria2->setTitle('product-detail-route');
        $criteria2->addFilter(
            new ProductAvailableFilter('', ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $filter = $this->productCloseoutFilterFactory->create($this->context);
        $filter->addQuery(new EqualsFilter('product.parentId', null));
        $criteria2->addFilter($filter);

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 4, new ProductCollection([$productEntity]), null, new Criteria(), $this->context->getContext())
            );

        $this->systemConfig->method('getBool')->willReturn(true);

        $result = $this->route->load($this->idsCollection->get('product2'), new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('BestVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testSkipConfiguratorQueryParameterExcludingConfigurator(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $request = new Request();

        $result = $this->route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertNotNull($result->getConfigurator());

        $request->query->set('skipConfigurator', true);

        $result = $this->route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertNull($result->getConfigurator());
    }

    public function testSkipCmsPageQueryParameterExcludingCmsPage(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');

        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $cmsPage = new CmsPageEntity();
        $cmsPage->setId('4');

        $this->cmsPageLoader->expects($this->once())
            ->method('load')
            ->willReturn(new EntitySearchResult(
                'cms_page',
                1,
                new CmsPageCollection([$cmsPage]),
                null,
                new Criteria(),
                $this->context->getContext()
            ));

        // Reset cmsPage of product
        $productEntity->assign(['cmsPage' => null]);

        $request = new Request();

        $result = $this->route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertSame($cmsPage, $result->getProduct()->getCmsPage());

        // Reset cmsPage of product
        $productEntity->assign(['cmsPage' => null]);

        $request->query->set('skipCmsPage', true);

        $result = $this->route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertNull($result->getProduct()->getCmsPage());
    }

    public function testLoadProductNotFound(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ProductNotFoundException::class);
        } else {
            $this->expectException(ProductException::class);
        }

        $this->route->load('1', new Request(), $this->context, new Criteria());
    }

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    #[DataProvider('breadcrumbCategoryDataProvider')]
    public function testLoadBreadcrumbCategory(
        SalesChannelProductEntity $product,
        bool $buildBreadcrumbByReferrerCategory,
        ?string $referrerCategoryId,
        InvokedCount $getProductSeoCategoryCount,
        InvokedCount $loadCategoryCount,
        ?CategoryEntity $breadcrumbCategory
    ): void {
        $this->productRepository->expects($this->exactly(1))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$product]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );
        $this->systemConfig->method('getBool')->willReturn($buildBreadcrumbByReferrerCategory);
        $this->breadcrumbBuilder->expects($getProductSeoCategoryCount)
            ->method('getProductSeoCategory')
            ->willReturn($breadcrumbCategory);
        $this->breadcrumbBuilder->expects($loadCategoryCount)
            ->method('loadCategory')
            ->willReturn($breadcrumbCategory);

        $request = new Request();

        if ($referrerCategoryId) {
            $request->query->set('referrerCategoryId', $referrerCategoryId);
        }

        $result = $this->route->load('1', $request, $this->context, new Criteria());

        static::assertSame($breadcrumbCategory, $result->getProduct()->getSeoCategory());
    }

    public static function breadcrumbCategoryDataProvider(): \Generator
    {
        $defaultBreadcrumbCategory = new CategoryEntity();
        $defaultBreadcrumbCategory->setId(Uuid::randomHex());
        $secondCategory = new CategoryEntity();
        $secondCategory->setId(Uuid::randomHex());
        $thirdCategory = new CategoryEntity();
        $thirdCategory->setId(Uuid::randomHex());

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setCategoryIds([$defaultBreadcrumbCategory->getId(), $secondCategory->getId()]);

        $productWithoutCategories = new SalesChannelProductEntity();
        $productWithoutCategories->setId(Uuid::randomHex());

        yield 'Load default breadcrumb category with disabled referrer feature' => [
            $product,
            false,
            null,
            new InvokedCount(1),
            new InvokedCount(0),
            $defaultBreadcrumbCategory,
        ];

        yield 'Load no breadcrumb category when product has no categories assigned' => [
            $productWithoutCategories,
            false,
            null,
            new InvokedCount(1),
            new InvokedCount(0),
            null,
        ];

        yield 'Load default breadcrumb category with enabled referrer feature and no referrerCategoryId' => [
            $product,
            true,
            null,
            new InvokedCount(1),
            new InvokedCount(0),
            $defaultBreadcrumbCategory,
        ];

        yield 'Load breadcrumb category by referrerCategoryId with enabled referrer feature' => [
            $product,
            true,
            $secondCategory->getId(),
            new InvokedCount(0),
            new InvokedCount(1),
            $secondCategory,
        ];

        yield 'Load default breadcrumb category with enabled referrer feature and unassigned referrerCategoryId' => [
            $product,
            true,
            $thirdCategory->getId(),
            new InvokedCount(1),
            new InvokedCount(0),
            $defaultBreadcrumbCategory,
        ];
    }
}
