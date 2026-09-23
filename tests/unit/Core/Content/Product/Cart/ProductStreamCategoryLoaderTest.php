<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Cart\ProductCategoryPathResolver;
use Shopware\Core\Content\Product\Cart\ProductStreamCategoryLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStreamCategoryLoader::class)]
class ProductStreamCategoryLoaderTest extends TestCase
{
    public function testProductsOnlyAssignedThroughAStreamGetTheStreamCategories(): void
    {
        $streamOnly = $this->product([], ['stream-sale']);
        $direct = $this->product([Uuid::randomHex()], ['stream-sale']);

        $sale = (new CategoryEntity())->assign(['id' => Uuid::randomHex(), 'productStreamId' => 'stream-sale']);
        $other = (new CategoryEntity())->assign(['id' => Uuid::randomHex(), 'productStreamId' => 'stream-other']);

        /** @var StaticEntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($sale, $other): CategoryCollection {
                static::assertEquals(
                    [
                        new EqualsAnyFilter('productStreamId', ['stream-sale']),
                        new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM),
                        new EqualsFilter('active', true),
                    ],
                    $criteria->getFilters()
                );

                return new CategoryCollection([$sale, $other]);
            },
        ]);

        (new ProductStreamCategoryLoader($categoryRepository))->load([$streamOnly, $direct], Generator::generateSalesChannelContext());

        $streamCategories = $streamOnly->getExtensionOfType(ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION, CategoryCollection::class);
        static::assertInstanceOf(CategoryCollection::class, $streamCategories);
        static::assertSame([$sale->getId()], array_values($streamCategories->getIds()));

        // the storefront breadcrumb only falls back to streams without a direct assignment
        static::assertFalse($direct->hasExtension(ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION));
    }

    public function testNoQueryWithoutStreamOnlyProducts(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->expects($this->never())->method('search');

        (new ProductStreamCategoryLoader($categoryRepository))->load(
            [$this->product([Uuid::randomHex()], ['stream-sale']), $this->product([], [])],
            Generator::generateSalesChannelContext()
        );
    }

    public function testProductsThatAlreadyCarryStreamCategoriesAreNotLoadedAgain(): void
    {
        $product = $this->product([], ['stream-sale']);
        $product->addExtension(ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION, new CategoryCollection());

        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->expects($this->never())->method('search');

        (new ProductStreamCategoryLoader($categoryRepository))->load([$product], Generator::generateSalesChannelContext());
    }

    /**
     * @param list<string> $categoryIds
     * @param list<string> $streamIds
     */
    private function product(array $categoryIds, array $streamIds): SalesChannelProductEntity
    {
        return (new SalesChannelProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'categoryIds' => $categoryIds,
            'streamIds' => $streamIds,
        ]);
    }
}
