<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductCategoryDenormalizerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
    }

    public function testUpdateWithProductAddedCategoriesWillVariantGetSameCategories(): void
    {
        ['products' => $productFixture, 'categories' => $categoryIds] = $this->prepareData();

        $this->productRepository->update([
            [
                'id' => $productFixture['testable-product'],
                'categories' => \array_map(static fn (string $categoryId) => ['id' => $categoryId], $categoryIds),
            ],
        ], $this->context);

        static::assertSame($categoryIds, $this->getProductCategoryList($productFixture['testable-product']));
        static::assertSame(
            \count($categoryIds),
            $this->getCountRowsInProductCategoryTree($productFixture['testable-product'], $categoryIds)
        );

        static::assertSame($categoryIds, $this->getProductCategoryList($productFixture['variant-testable-product']));
        static::assertSame(
            \count($categoryIds),
            $this->getCountRowsInProductCategoryTree($productFixture['variant-testable-product'], $categoryIds)
        );
    }

    public function testFullIndexIncludesParentCategoriesInTheProductCategoryTree(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $rootCategoryId = Uuid::randomHex();
        $childCategoryId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $rootCategoryId, 'name' => 'Root'],
            ['id' => $childCategoryId, 'parentId' => $rootCategoryId, 'name' => 'Child'],
        ], $context);

        $productId = Uuid::randomHex();
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productId,
                'stock' => 1,
                'name' => 'Product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'Tax', 'taxRate' => 19],
                'categories' => [['id' => $childCategoryId]],
            ],
        ], $context);

        static::getContainer()->get(EntityIndexerRegistry::class)->index(false);

        static::assertSame(1, $this->getCountRowsInProductCategoryTree($productId, [$rootCategoryId]));
    }

    #[DataProvider('categoryTreeSizes')]
    public function testBatchUpdateReplacesCategoryTreesAndClearsMissingCategories(int $ancestorCount): void
    {
        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $ancestorIds = [];
        for ($index = 0; $index < $ancestorCount; ++$index) {
            $id = Uuid::randomHex();
            $ancestorIds[] = $id;
            $this->connection->insert('category', [
                'id' => Uuid::fromHexToBytes($id),
                'version_id' => $version,
                'type' => 'page',
                'created_at' => '2026-01-01 00:00:00.000',
            ]);
        }
        $categoryId = Uuid::randomBytes();
        $this->connection->insert('category', [
            'id' => $categoryId,
            'version_id' => $version,
            'type' => 'page',
            'path' => $ancestorIds === [] ? null : '|' . implode('|', $ancestorIds) . '|',
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $ids = [];
        $expected = [];

        for ($index = 0; $index < 501; ++$index) {
            $id = Uuid::randomHex();
            $ids[] = $id;
            $this->connection->insert('product', [
                'id' => Uuid::fromHexToBytes($id),
                'version_id' => $version,
                'product_number' => $id,
                'stock' => 1,
                'categories' => Uuid::fromHexToBytes($id),
                'category_tree' => json_encode([Uuid::randomHex()], \JSON_THROW_ON_ERROR),
            ]);
            $expected[$id] = null;
            if ($index % 2 === 0) {
                $this->connection->insert('product_category', [
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => $version,
                    'category_id' => $categoryId,
                    'category_version_id' => $version,
                ]);
                $expected[$id] = [...$ancestorIds, Uuid::fromBytesToHex($categoryId)];
            }
        }

        (new ProductCategoryDenormalizer($this->connection))->update($ids, $this->context);

        $trees = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), category_tree FROM product WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $version],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(501, $trees);
        foreach ($trees as $id => $tree) {
            static::assertSame($expected[$id], $tree === null ? null : json_decode($tree, true, 512, \JSON_THROW_ON_ERROR));
        }
        static::assertSame(251 * ($ancestorCount + 1), (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_category_tree WHERE product_id IN (:ids) AND product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $version],
            ['ids' => ArrayParameterType::BINARY]
        ));
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function categoryTreeSizes(): \Generator
    {
        yield 'small trees cross the row limit' => [0];
        yield 'category inserts cross the row limit' => [1];
        yield 'large trees reduce the batch size' => [200];
    }

    /**
     * @return array<string>|null
     */
    private function getProductCategoryList(string $productId): ?array
    {
        $productRepository = static::getContainer()->get('product.repository');
        $testableProduct = $productRepository->search(new Criteria([$productId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $testableProduct);

        $productCategoryIds = $testableProduct->getCategoryTree();
        if ($productCategoryIds !== null) {
            \sort($productCategoryIds);
        }

        return $productCategoryIds;
    }

    /**
     * @param list<string> $categoryIds
     */
    private function getCountRowsInProductCategoryTree(string $productId, array $categoryIds): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) as cnt FROM product_category_tree WHERE product_id = :productId AND category_id IN (:categoryIds)',
            [
                'productId' => Uuid::fromHexToBytes($productId),
                'categoryIds' => Uuid::fromHexToBytesList($categoryIds),
            ],
            ['categoryIds' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return array{products: array{product-with-category: string, testable-product: string, variant-testable-product: string}, categories: list<string>}
     */
    private function prepareData(): array
    {
        $ids = new IdsCollection();

        $products = [];

        $name = 'product-with-category';
        $builder = new ProductBuilder($ids, $name);
        $builder->price(200)
            ->categories(['cat1', 'cat2'])
            ->write(static::getContainer());
        /** @var array{id: string, children: array<int, array{id: string}>, categories: array<int, array{id: string, name:string}>} $product */
        $product = $builder->build();
        $products[$name] = $product['id'];
        $categories = \array_column($product['categories'], 'id');
        \sort($categories);

        $name = 'testable-product';
        $builder = new ProductBuilder($ids, $name);
        $builder->price(100)
            ->variant(
                (new ProductBuilder($ids, 'variant-testable-product'))
                ->price(100)->build()
            )
            ->write(static::getContainer());

        $product = $builder->build();
        $products[$name] = $product['id'];
        $products['variant-testable-product'] = $product['children'][0]['id'];

        static::assertSame(
            0,
            $this->getCountRowsInProductCategoryTree($products['variant-testable-product'], $categories)
        );

        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }
}
