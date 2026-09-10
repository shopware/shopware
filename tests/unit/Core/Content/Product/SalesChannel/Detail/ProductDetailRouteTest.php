<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
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
#[Package('inventory')]
#[CoversClass(ProductDetailRoute::class)]
class ProductDetailRouteTest extends TestCase
{
    /**
     * @var Stub&SalesChannelRepository<SalesChannelProductCollection>
     */
    private SalesChannelRepository $productRepository;

    /**
     * @var Stub&SystemConfigService
     */
    private SystemConfigService $systemConfig;

    /**
     * @var Stub&EntityRepository<ProductTranslationCollection>
     */
    private Stub&EntityRepository $productTranslationRepository;

    private Connection&Stub $connection;

    private ProductDetailRoute $route;

    private SalesChannelContext $context;

    private IdsCollection $idsCollection;

    private CategoryBreadcrumbBuilder&Stub $breadcrumbBuilder;

    private SalesChannelCmsPageLoader&Stub $cmsPageLoader;

    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Generator::generateSalesChannelContext();
        $this->idsCollection = new IdsCollection();
        $this->productRepository = static::createStub(SalesChannelRepository::class);
        $this->productTranslationRepository = static::createStub(EntityRepository::class);
        $this->systemConfig = static::createStub(SystemConfigService::class);
        $this->connection = static::createStub(Connection::class);
        $this->breadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $this->cmsPageLoader = static::createStub(SalesChannelCmsPageLoader::class);
        $this->productCloseoutFilterFactory = new ProductCloseoutFilterFactory();
        $this->eventDispatcher = new EventDispatcher();

        $this->route = $this->buildRoute();
    }

    public function testLoadMainVariant(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->exactly(1))
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
        $route = $this->buildRoute($productRepository);

        $result = $route->load('1', new Request(), $this->context, new Criteria());

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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));

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
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );
        $productRepository->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 4, new ProductCollection([$productEntity]), null, new Criteria(), $this->context->getContext())
            );
        $route = $this->buildRoute($productRepository);

        $result = $route->load($product1Id, new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('BestVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testLoadParentSearchUsesMatchedVariantWhenFindBestVariantEnabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
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
        $productTerm->internalSetEntityData('product', new FieldVisibility([]));

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
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($product1Id): void {
            static::assertSame($product1Id, $event->getResolvedVariantId());
        });

        $productRepository->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 4, new ProductCollection([$productTerm]), null, new Criteria(), $this->context->getContext())
            );
        $request = new Request();
        $request->query->set('search', 'term');

        $route = $this->buildRoute($productRepository, $connection);
        $result = $route->load($product1Id, $request, $this->context, new Criteria());

        static::assertSame('term', $result->getProduct()->getCmsPageId());
        static::assertSame('term', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadParentSearchKeepsMainVariantWhenFindBestVariantDisabled(): void
    {
        $mainVariantId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection
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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($mainVariantId): void {
            static::assertSame($mainVariantId, $event->getResolvedVariantId());
        });

        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->never())
            ->method('searchIds');
        $productRepository->expects($this->once())
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

        $result = $this->buildRoute($productRepository, $connection)->load(Uuid::randomHex(), $request, $this->context, new Criteria());

        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadVariantListingConfig(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->once())
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

        $result = $this->buildRoute($productRepository, $connection)->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testLoadFallsBackToBestVariantWhenConfiguredMainVariantIsNotAvailable(): void
    {
        $productId = Uuid::randomHex();
        $mainVariantId = Uuid::randomHex();
        $bestVariantId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": false, "mainVariantId": "' . $mainVariantId . '"}',
                'parentId' => null,
            ]);

        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId($bestVariantId);
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('best-variant');
        $productEntity->setAvailable(true);
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(
                1,
                [
                    $bestVariantId => [
                        'primaryKey' => $bestVariantId,
                        'data' => [],
                    ],
                ],
                new Criteria(),
                $this->context->getContext()
            ));
        $searchCall = 0;
        $productRepository->expects($this->exactly(2))
            ->method('search')
            ->with(static::callback(function (Criteria $criteria) use (&$searchCall, $mainVariantId, $bestVariantId): bool {
                ++$searchCall;
                static::assertSame($searchCall === 1 ? [$mainVariantId] : [$bestVariantId], $criteria->getIds());

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    'product',
                    0,
                    new ProductCollection(),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                ),
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $result = $this->buildRoute($productRepository, $connection)->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('best-variant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testResolveVariantIdFromEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->once())
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

        $result = $this->buildRoute($productRepository, $connection)->load(Uuid::randomHex(), new Request(), $this->context, new Criteria());

        static::assertSame($variantId, $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testResolveVariantIdFromEventWithWrongTypeForDisplayParent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->once())
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

        $result = $this->buildRoute($productRepository, $connection)->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testResolveVariantIdFromEventWithDisplayParent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->once())
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

        $result = $this->buildRoute($productRepository, $connection)->load($productId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getProduct()->getUniqueIdentifier());
        static::assertTrue($result->getProduct()->getAvailable());
    }

    public function testConfigHideCloseoutProductsWhenOutOfStockFiltersResults(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('BestVariant');
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));

        $criteria2 = new Criteria([$this->idsCollection->get('product2')]);
        $criteria2->setTitle('product-detail-route');
        $criteria2->addFilter(
            new ProductAvailableFilter('', ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $filter = $this->productCloseoutFilterFactory->create($this->context);
        $filter->addQuery(new EqualsFilter('product.parentId', null));
        $criteria2->addFilter($filter);

        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 4, new ProductCollection([$productEntity]), null, new Criteria(), $this->context->getContext())
            );

        $this->systemConfig->method('getBool')->willReturn(true);

        $result = $this->buildRoute($productRepository)->load($this->idsCollection->get('product2'), new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('BestVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testSkipConfiguratorQueryParameterExcludingConfigurator(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->exactly(2))
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

        $route = $this->buildRoute($productRepository);
        $request = new Request();

        $result = $route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertNotNull($result->getConfigurator());

        $request->query->set('skipConfigurator', true);

        $result = $route->load('1', $request, $this->context, new Criteria());

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
        $productEntity->internalSetEntityData('product', new FieldVisibility([]));

        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->exactly(2))
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

        $cmsPageLoader = $this->createMock(SalesChannelCmsPageLoader::class);
        $cmsPageLoader->expects($this->once())
            ->method('load')
            ->willReturn(new EntitySearchResult(
                'cms_page',
                1,
                new CmsPageCollection([$cmsPage]),
                null,
                new Criteria(),
                $this->context->getContext()
            ));

        $route = $this->buildRoute($productRepository, cmsPageLoader: $cmsPageLoader);

        // Reset cmsPage of product
        $productEntity->assign(['cmsPage' => null]);

        $request = new Request();

        $result = $route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getProduct()->getCmsPageId());
        static::assertSame('mainVariant', $result->getProduct()->getUniqueIdentifier());
        static::assertSame($cmsPage, $result->getProduct()->getCmsPage());

        // Reset cmsPage of product
        $productEntity->assign(['cmsPage' => null]);

        $request->query->set('skipCmsPage', true);

        $result = $route->load('1', $request, $this->context, new Criteria());

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
        InvokedCount $getProductCategoryByReferrerCount,
        InvokedCount $getProductSeoCategoryCount,
        ?CategoryEntity $breadcrumbCategory
    ): void {
        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->exactly(1))
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
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($getProductCategoryByReferrerCount)
            ->method('getProductCategoryByReferrer')
            ->willReturn($breadcrumbCategory);
        $breadcrumbBuilder->expects($getProductSeoCategoryCount)
            ->method('getProductSeoCategory')
            ->willReturn($breadcrumbCategory);

        $request = new Request();

        if ($referrerCategoryId) {
            $request->query->set('referrerCategoryId', $referrerCategoryId);
        }

        $result = $this->buildRoute($productRepository, breadcrumbBuilder: $breadcrumbBuilder)->load('1', $request, $this->context, new Criteria());

        static::assertSame($breadcrumbCategory, $result->getProduct()->getSeoCategory());
    }

    public static function breadcrumbCategoryDataProvider(): \Generator
    {
        $defaultBreadcrumbCategory = new CategoryEntity();
        $defaultBreadcrumbCategory->setId(Uuid::randomHex());
        $parentCategory = new CategoryEntity();
        $parentCategory->setId(Uuid::randomHex());
        $secondCategory = new CategoryEntity();
        $secondCategory->setId(Uuid::randomHex());
        $thirdCategory = new CategoryEntity();
        $thirdCategory->setId(Uuid::randomHex());

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setCategoryIds([$defaultBreadcrumbCategory->getId(), $secondCategory->getId()]);
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $productWithoutCategories = new SalesChannelProductEntity();
        $productWithoutCategories->setId(Uuid::randomHex());
        $productWithoutCategories->internalSetEntityData('product', new FieldVisibility([]));

        yield 'Load default breadcrumb category with disabled referrer feature' => [
            $product,
            false,
            null,
            new InvokedCount(0),
            new InvokedCount(1),
            $defaultBreadcrumbCategory,
        ];

        yield 'Load no breadcrumb category when product has no categories assigned' => [
            $productWithoutCategories,
            false,
            null,
            new InvokedCount(0),
            new InvokedCount(1),
            null,
        ];

        yield 'Load default breadcrumb category with enabled referrer feature and no referrerCategoryId' => [
            $product,
            true,
            null,
            new InvokedCount(0),
            new InvokedCount(1),
            $defaultBreadcrumbCategory,
        ];

        yield 'Load breadcrumb category by referrerCategoryId with enabled referrer feature' => [
            $product,
            true,
            $secondCategory->getId(),
            new InvokedCount(1),
            new InvokedCount(0),
            $secondCategory,
        ];

        yield 'Load breadcrumb category by referrerCategoryId with enabled referrer feature and referrerCategoryId being a parent of a category assigned to the product' => [
            $product,
            true,
            $parentCategory->getId(),
            new InvokedCount(1),
            new InvokedCount(0),
            $parentCategory,
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

    /**
     * @param (SalesChannelRepository<SalesChannelProductCollection>&MockObject)|null $productRepository
     */
    private function buildRoute(
        ?SalesChannelRepository $productRepository = null,
        ?Connection $connection = null,
        ?CategoryBreadcrumbBuilder $breadcrumbBuilder = null,
        ?SalesChannelCmsPageLoader $cmsPageLoader = null,
    ): ProductDetailRoute {
        return new ProductDetailRoute(
            $productRepository ?? $this->productRepository,
            $this->productTranslationRepository,
            $this->systemConfig,
            $connection ?? $this->connection,
            static::createStub(ProductConfiguratorLoader::class),
            $breadcrumbBuilder ?? $this->breadcrumbBuilder,
            $cmsPageLoader ?? $this->cmsPageLoader,
            static::createStub(EntityCmsSlotConfigInheritanceBuilder::class),
            new SalesChannelProductDefinition(),
            $this->productCloseoutFilterFactory,
            $this->eventDispatcher,
            static::createStub(CacheTagCollector::class),
        );
    }
}
