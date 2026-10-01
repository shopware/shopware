<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryBreadcrumbBuilder::class)]
class CategoryBreadcrumbBuilderTest extends TestCase
{
    protected SalesChannelContext $salesChannelContext;

    private EntityRouteResolver $entityRouteResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salesChannelContext = $this->getSalesChannelContext();
        $entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $entityRouteResolver->method('getRouteNameForEntityName')->willReturn('frontend.navigation.page');
        $this->entityRouteResolver = $entityRouteResolver;
    }

    public function testGetProductSeoCategoryShouldReturnMainCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], [$categoryEntity]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);

        static::assertNotNull($categoryEntity);
    }

    public function testGetProductSeoCategoryMissingCategoryIdsAndStreamIds(): void
    {
        $categoryIds = [];
        $streamIds = null;

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('');
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], [$categoryEntity]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);

        static::assertNull($categoryEntity);
    }

    public function testGetProductSeoCategoryHasCategoryIdsAndStreamIds(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];
        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([], []),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);

        static::assertNull($categoryEntity);
    }

    public function testGetProductSeoCategoryShouldReturnProductCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], []),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);

        static::assertNotNull($categoryEntity);
    }

    public function testGetProductSeoCategoryShouldReturnProductStreamCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], []),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $product = $this->getProductEntity($streamIds, []);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);

        static::assertNotNull($categoryEntity);
    }

    public function testGetProductSeoCategoryShouldPreferDeepestVisibleActiveCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryRepositoryMock = $this->createMock(EntityRepository::class);
        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $categoryRepositoryMock,
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $product = $this->getProductEntity([], $categoryIds);

        $context = $this->salesChannelContext->getContext();
        $categoryRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use ($categoryEntity, $context): EntitySearchResult {
                $sortings = $criteria->getSorting();

                static::assertCount(2, $sortings);
                static::assertSame('visible', $sortings[0]->getField());
                static::assertSame(FieldSorting::DESCENDING, $sortings[0]->getDirection());
                static::assertSame('level', $sortings[1]->getField());
                static::assertSame(FieldSorting::DESCENDING, $sortings[1]->getDirection());

                static::assertContains('active', $criteria->getFilterFields());
                static::assertNotContains('visible', $criteria->getFilterFields());

                return new EntitySearchResult('category', 1, new CategoryCollection([$categoryEntity]), null, $criteria, $context);
            });

        $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);
    }

    public function testGetProductSeoCategoryShouldReturnMainCategoryHiddenInNavigation(): void
    {
        $categoryId = Uuid::randomHex();

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryId);
        $categoryEntity->setName('hidden-main-category');
        $categoryEntity->setActive(true);
        $categoryEntity->setVisible(false);
        $categoryEntity->setPath('|navigationCategoryId|');

        $mainCategory = new MainCategoryEntity();
        $mainCategory->setId(Uuid::randomHex());
        $mainCategory->setSalesChannelId($this->salesChannelContext->getSalesChannelId());
        $mainCategory->setCategory($categoryEntity);

        $product = $this->getProductEntity([], [$categoryId]);
        $product->setMainCategories(new MainCategoryCollection([$mainCategory]));

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([], []),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        static::assertSame($categoryEntity, $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext));
    }

    public function testGetProductCategoryByReferrerShouldReturnReferrerCategoryHiddenInNavigation(): void
    {
        $categoryId = Uuid::randomHex();

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryId);
        $categoryEntity->setName('hidden-referrer-category');
        $categoryEntity->setActive(true);
        $categoryEntity->setVisible(false);
        $categoryEntity->setPath('|navigationCategoryId|');

        $product = $this->getProductEntity([], [$categoryId]);
        $product->setCategoryTree([$categoryId]);

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], []),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        static::assertSame($categoryEntity, $categoryBreadcrumbBuilder->getProductCategoryByReferrer($categoryId, $product, $this->salesChannelContext));
    }

    public function testConvertCategoriesToBreadcrumbUrlsWithSeoUrls(): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c01',
            'Home 2',
            [
                'name' => 'Home sweet home 2',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c01', $this->salesChannelContext->getContext());
        static::assertNotNull($category);
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->salesChannelContext->getContext(), $this->salesChannelContext->getSalesChannel())->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home 2', $firstBreadcrumb->name);
        static::assertSame('seoPathInfo/1', $firstBreadcrumb->path);
        static::assertCount(1, $firstBreadcrumb->seoUrls);
    }

    public function testConvertCategoriesToBreadcrumbUrlsWithSeoUrlsOnlyPathInfo(): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c02',
            'Home',
            [
                'name' => 'Home sweet home',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c02', $this->salesChannelContext->getContext());
        static::assertNotNull($category);
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->salesChannelContext->getContext(), $this->salesChannelContext->getSalesChannel())->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame('pathInfo/1', $firstBreadcrumb->path);
        static::assertCount(1, $firstBreadcrumb->seoUrls);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function breadcrumbWithoutSeoUrlDataProvider(): iterable
    {
        yield 'page category has a navigation fallback path' => [
            CategoryDefinition::TYPE_PAGE,
            'navigation/019192b9cd82711482744d7b456b6c03',
        ];

        yield 'folder category has no navigable path' => [
            CategoryDefinition::TYPE_FOLDER,
            '',
        ];
    }

    #[DataProvider('breadcrumbWithoutSeoUrlDataProvider')]
    public function testConvertCategoriesToBreadcrumbUrlsWithNoSeoUrls(string $categoryType, string $expectedPath): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c03',
            'Home',
            [
                'name' => 'Home sweet home',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );
        $categoryEntityOne->setType($categoryType);

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock([]),
            $this->entityRouteResolver,
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c03', $this->salesChannelContext->getContext());
        static::assertNotNull($category);
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->salesChannelContext->getContext(), $this->salesChannelContext->getSalesChannel())->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame($expectedPath, $firstBreadcrumb->path);
        static::assertSame([], $firstBreadcrumb->seoUrls);
    }

    // write a test to cover getProductBreadcrumbUrls method
    public function testGetProductBreadcrumbUrls(): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c03',
            'Home',
            [
                'name' => 'Home sweet home',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );

        $product = $this->getProductEntity([], ['019192b9cd82711482744d7b456b6c03']);
        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getProductRepositoryMock([$product], [$product]),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $result = $categoryBreadcrumbBuilder->getProductBreadcrumbUrls($product->getId(), '', $this->salesChannelContext)->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame('navigation/1', $firstBreadcrumb->path);
    }

    public function testGetProductSeoCategoryWithNoMainCategoryAndNoCategoryIds(): void
    {
        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setMainCategories(new MainCategoryCollection([]));
        $productEntity->setCategoryIds([]);
        $productEntity->setStreamIds([]);

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([], []),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $result = $categoryBreadcrumbBuilder->getProductSeoCategory($productEntity, $this->salesChannelContext);

        static::assertNull($result);
    }

    /**
     * @param array<int, array{categoryId: string, pathInfo: string, seoPathInfo: string}> $seoUrls
     */
    private function getConnectionMock(array $seoUrls = [
        [
            'categoryId' => '019192b9cd82711482744d7b456b6c01',
            'pathInfo' => 'pathInfo/1',
            'seoPathInfo' => 'seoPathInfo/1',
        ],
        [
            'categoryId' => '019192b9cd82711482744d7b456b6c02',
            'pathInfo' => 'pathInfo/1',
            'seoPathInfo' => '',
        ],
        [
            'categoryId' => '019192b9cd82711482744d7b456b6c03',
            'pathInfo' => 'navigation/1',
            'seoPathInfo' => '',
        ],
    ]): Connection
    {
        $connection = static::createStub(Connection::class);
        $queryBuilder = static::createStub(QueryBuilder::class);
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($seoUrls);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }

    /**
     * @param array<string, mixed> $translated
     */
    private function createNewCategoryEntity(
        string $id,
        string $name,
        array $translated,
    ): CategoryEntity {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($id);
        $categoryEntity->setName($name);
        $categoryEntity->setTranslated($translated);
        $categoryEntity->setType('page');

        return $categoryEntity;
    }

    /**
     * @param array<CategoryEntity> $categoryEntityCollection1
     * @param array<CategoryEntity> $categoryEntityCollection2
     *
     * @return EntityRepository<CategoryCollection>
     */
    private function getCategoryRepositoryMock(array $categoryEntityCollection1, array $categoryEntityCollection2): EntityRepository
    {
        $categoryRepositoryMock = static::createStub(EntityRepository::class);
        $categoryRepositoryMock->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection1), null, new Criteria(), $this->salesChannelContext->getContext()),
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection2), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        return $categoryRepositoryMock;
    }

    /**
     * @param array<ProductEntity> $productEntityCollection1
     * @param array<ProductEntity> $productEntityCollection2
     *
     * @return SalesChannelRepository<SalesChannelProductCollection>
     */
    private function getProductRepositoryMock(array $productEntityCollection1, array $productEntityCollection2): SalesChannelRepository
    {
        $productRepositoryMock = static::createStub(SalesChannelRepository::class);
        $productRepositoryMock->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('product', 1, new ProductCollection($productEntityCollection1), null, new Criteria(), $this->salesChannelContext->getContext()),
            new EntitySearchResult('product', 1, new ProductCollection($productEntityCollection2), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        return $productRepositoryMock;
    }

    /**
     * @param array<string> $streamIds
     * @param array<string>|null $categoryIds
     */
    private function getProductEntity(?array $streamIds, ?array $categoryIds): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();

        $product->setId(Uuid::randomHex());
        $product->setStreamIds($streamIds);
        $product->setCategoryIds($categoryIds);
        $product->internalSetEntityData('product', new FieldVisibility([]));

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId(Uuid::randomHex());
        $salesChannelEntity->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannelEntity->setNavigationCategoryId('navigationCategoryId');
        $salesChannelEntity->setServiceCategoryId('serviceCategoryId');
        $salesChannelEntity->setFooterCategoryId('footerCategoryId');

        return Generator::generateSalesChannelContext(salesChannel: $salesChannelEntity);
    }
}
