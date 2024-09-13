<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(CategoryBreadcrumbBuilder::class)]
class CategoryBreadcrumbBuilderTest extends TestCase
{
    protected SalesChannelContext $context;

    protected MockObject&RouterInterface $router;

    protected MockObject&AbstractSeoResolver $seoResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->getSalesChannelContext();
        $this->router = $this->createMock(RouterInterface::class);
        $this->seoResolver = $this->createMock(AbstractSeoResolver::class);
    }

    public function testGetProductSeoCategoryShouldReturnMainCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder($this->getCategoryRepositoryMock([$categoryEntity], [$categoryEntity]), $this->router, $this->seoResolver);
        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->context);

        static::assertNotNull($categoryEntity);
    }

    public function testGetProductSeoCategoryMissingCategoryIdsAndStreamIds(): void
    {
        $categoryIds = [];
        $streamIds = null;

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('');
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder($this->getCategoryRepositoryMock([$categoryEntity], [$categoryEntity]), $this->router, $this->seoResolver);
        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->context);

        static::assertNull($categoryEntity);
    }

    public function testGetProductSeoCategoryHasCategoryIdsAndStreamIds(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder($this->getCategoryRepositoryMock([], []), $this->router, $this->seoResolver);
        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->context);

        static::assertNull($categoryEntity);
    }

    public function testGetProductSeoCategoryShouldReturnProductCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder($this->getCategoryRepositoryMock([], [$categoryEntity]), $this->router, $this->seoResolver);
        $product = $this->getProductEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->context);

        static::assertNotNull($categoryEntity);
    }

    public function testGetProductSeoCategoryShouldReturnProductStreamCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder($this->getCategoryRepositoryMock([], [$categoryEntity]), $this->router, $this->seoResolver);
        $product = $this->getProductEntity($streamIds, []);
        $categoryEntity = $categoryBreadcrumbBuilder->getProductSeoCategory($product, $this->context);

        static::assertNotNull($categoryEntity);
    }

    /**
     * @param array<CategoryEntity> $categoryEntityCollection1
     * @param array<CategoryEntity> $categoryEntityCollection2
     */
    private function getCategoryRepositoryMock(array $categoryEntityCollection1, array $categoryEntityCollection2): EntityRepository
    {
        $categoryRepositoryMock = $this->createMock(EntityRepository::class);
        $categoryRepositoryMock->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection1), null, new Criteria(), $this->context->getContext()),
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection2), null, new Criteria(), $this->context->getContext()),
        );

        return $categoryRepositoryMock;
    }

    /**
     * @param array<string> $streamIds
     * @param array<string>|null $categoryIds
     */
    private function getProductEntity(?array $streamIds, ?array $categoryIds): ProductEntity
    {
        $product = new ProductEntity();

        $product->setId(Uuid::randomHex());
        $product->setStreamIds($streamIds);
        $product->setCategoryIds($categoryIds);

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');
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
