<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\ProductSeoCategoryResolver;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSeoCategoryResolver::class)]
class ProductSeoCategoryResolverTest extends TestCase
{
    public function testReturnsNoCategoriesWithoutProductsOrAssignments(): void
    {
        $connection = static::createStub(Connection::class);
        $resolver = new ProductSeoCategoryResolver(new StaticSalesChannelRepository([new CategoryCollection()]), $connection);
        $context = Generator::generateSalesChannelContext();
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());

        static::assertSame([], $resolver->resolve([], $context));
        static::assertSame([], $resolver->resolve([$product], $context));
    }

    #[DataProvider('productCounts')]
    public function testCategoryReadsDoNotGrowWithTheNumberOfProducts(int $count): void
    {
        $category = $this->category();
        $streamCategory = $this->category();
        $streamCategory->setProductStreamId(Uuid::randomHex());
        $products = [];
        $expected = [];
        for ($i = 0; $i < $count; ++$i) {
            $assigned = new ProductEntity();
            $assigned->setId(Uuid::randomHex());
            $assigned->setCategoryIds([$category->getId()]);
            $products[] = $assigned;
            $expected[$assigned->getId()] = $category;

            $dynamic = new ProductEntity();
            $dynamic->setId(Uuid::randomHex());
            $dynamic->setStreamIds([$streamCategory->getProductStreamId()]);
            $products[] = $dynamic;
            $expected[$dynamic->getId()] = $streamCategory;
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([]);
        // Exactly two category reads are configured, regardless of the number of products.
        $repository = new StaticSalesChannelRepository([
            new CategoryCollection([$category]),
            new CategoryCollection([$streamCategory]),
        ]);
        $resolver = new ProductSeoCategoryResolver($repository, $connection);

        static::assertEquals($expected, $resolver->resolve($products, Generator::generateSalesChannelContext()));
    }

    public static function productCounts(): \Generator
    {
        yield 'two products' => [1];
        yield 'forty-eight products' => [24];
    }

    public function testInheritsTheMainCategoryAndPrefersItOverTheFallback(): void
    {
        $fallback = $this->category();
        $main = $this->category();
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setParentId(Uuid::randomHex());
        $product->setCategoryIds([$fallback->getId(), $main->getId()]);
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$product->getParentId() => $main->getId()]);
        $resolver = new ProductSeoCategoryResolver(new StaticSalesChannelRepository([new CategoryCollection([$fallback, $main])]), $connection);

        static::assertSame([$product->getId() => $main], $resolver->resolve([$product], Generator::generateSalesChannelContext()));
    }

    public function testExcludesCategoriesBlockedAfterLoadingIncludingAPreloadedMainCategory(): void
    {
        $context = Generator::generateSalesChannelContext();
        $blocked = $this->category();
        $blocked->setActive(false);
        $allowed = $this->category();
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setCategoryIds([$blocked->getId(), $allowed->getId()]);
        $main = new MainCategoryEntity();
        $main->setId(Uuid::randomHex());
        $main->setSalesChannelId($context->getSalesChannelId());
        $main->setCategory(clone $blocked);
        $main->getCategory()?->setActive(true);
        $product->setMainCategories(new MainCategoryCollection([$main]));
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$product->getId() => $blocked->getId()]);
        $resolver = new ProductSeoCategoryResolver(new StaticSalesChannelRepository([new CategoryCollection([$blocked, $allowed])]), $connection);

        static::assertSame([$product->getId() => $allowed], $resolver->resolve([$product], $context));
    }

    public function testExcludesBlockedDynamicCategoriesAndUnassignedMainCategories(): void
    {
        $blocked = $this->category();
        $blocked->setActive(false);
        $blocked->setProductStreamId(Uuid::randomHex());
        $allowed = $this->category();
        $allowed->setProductStreamId($blocked->getProductStreamId());
        $unassignedMain = $this->category();
        $assigned = new ProductEntity();
        $assigned->setId(Uuid::randomHex());
        $assigned->setCategoryIds([$allowed->getId()]);
        $dynamic = new ProductEntity();
        $dynamic->setId(Uuid::randomHex());
        $dynamic->setStreamIds([$blocked->getProductStreamId()]);
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$assigned->getId() => $unassignedMain->getId()]);
        $resolver = new ProductSeoCategoryResolver(new StaticSalesChannelRepository([
            new CategoryCollection([$unassignedMain, $allowed]),
            new CategoryCollection([$blocked, $allowed]),
        ]), $connection);

        static::assertSame(
            [$assigned->getId() => $allowed, $dynamic->getId() => $allowed],
            $resolver->resolve([$assigned, $dynamic], Generator::generateSalesChannelContext())
        );
    }

    private function category(): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        return $category;
    }
}
