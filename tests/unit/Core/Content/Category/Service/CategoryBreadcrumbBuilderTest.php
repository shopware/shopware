<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @internal
 */
#[CoversClass(CategoryBreadcrumbBuilder::class)]
class CategoryBreadcrumbBuilderTest extends TestCase
{
    protected SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salesChannelContext = $this->getSalesChannelContext();
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
            $this->getConnectionMock()
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
            $this->getConnectionMock()
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
            $this->getConnectionMock()
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
            $this->getCategoryRepositoryMock([], [$categoryEntity]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock()
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
            $this->getCategoryRepositoryMock([], [$categoryEntity]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock()
        );
        $product = $this->getProductEntity($streamIds, []);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);

        static::assertNotNull($categoryEntity);
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
            $this->getConnectionMock()
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c01', $this->salesChannelContext->getContext());
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->salesChannelContext->getContext(), $this->salesChannelContext->getSalesChannel());
        /** @var \Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb $firstBreadcrumb */
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
            $this->getConnectionMock()
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c02', $this->salesChannelContext->getContext());
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->salesChannelContext->getContext(), $this->salesChannelContext->getSalesChannel());
        /** @var \Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb $firstBreadcrumb */
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame('pathInfo/1', $firstBreadcrumb->path);
        static::assertCount(1, $firstBreadcrumb->seoUrls);
    }

    public function testConvertCategoriesToBreadcrumbUrlsWithNoSeoUrls(): void
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

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getProductRepositoryMock([], []),
            $this->getConnectionMock()
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c03', $this->salesChannelContext->getContext());
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->salesChannelContext->getContext(), $this->salesChannelContext->getSalesChannel());
        /** @var \Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb $firstBreadcrumb */
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame('navigation/1', $firstBreadcrumb->path);
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
            $this->getConnectionMock()
        );

        $result = $categoryBreadcrumbBuilder->getProductBreadcrumbUrls($product->getId(), '', $this->salesChannelContext);
        /** @var \Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb $firstBreadcrumb */
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
            $this->getConnectionMock()
        );
        $result = $categoryBreadcrumbBuilder->getProductSeoCategory($productEntity, $this->salesChannelContext);

        static::assertNull($result);
    }

    private function getConnectionMock(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn(
            [
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
            ]
        );

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
     */
    private function getCategoryRepositoryMock(array $categoryEntityCollection1, array $categoryEntityCollection2): EntityRepository
    {
        $categoryRepositoryMock = $this->createMock(EntityRepository::class);
        $categoryRepositoryMock->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection1), null, new Criteria(), $this->salesChannelContext->getContext()),
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection2), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        return $categoryRepositoryMock;
    }

    /**
     * @param array<ProductEntity> $productEntityCollection1
     * @param array<ProductEntity> $productEntityCollection2
     */
    private function getProductRepositoryMock(array $productEntityCollection1, array $productEntityCollection2): SalesChannelRepository
    {
        $productRepositoryMock = $this->createMock(SalesChannelRepository::class);
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
    private function getProductEntity(?array $streamIds, ?array $categoryIds): ProductEntity
    {
        $product = new SalesChannelProductEntity();

        $product->setId(Uuid::randomHex());
        $product->setStreamIds($streamIds);
        $product->setCategoryIds($categoryIds);

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId(Uuid::randomHex());
        $salesChannelEntity->setNavigationCategoryId('navigationCategoryId');
        $salesChannelEntity->setServiceCategoryId('serviceCategoryId');
        $salesChannelEntity->setFooterCategoryId('footerCategoryId');

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
            new CustomerEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            []
        );
    }
}
