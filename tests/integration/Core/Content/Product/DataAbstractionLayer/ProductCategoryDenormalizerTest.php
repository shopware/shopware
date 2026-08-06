<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

    public function testUpdateRepairsCategoryTreeColumnWhenItDriftedFromTheRows(): void
    {
        ['products' => $productFixture, 'categories' => $categoryIds] = $this->prepareData();
        $productId = $productFixture['testable-product'];

        $this->productRepository->update([
            [
                'id' => $productId,
                'categories' => \array_map(static fn (string $categoryId) => ['id' => $categoryId], $categoryIds),
            ],
        ], $this->context);

        static::assertSame($categoryIds, $this->getProductCategoryList($productId));

        // the rows stay correct while the denormalized column is lost
        $this->connection->executeStatement(
            'UPDATE product SET category_tree = NULL WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($productId)]
        );

        static::getContainer()->get(ProductCategoryDenormalizer::class)->update([$productId], $this->context);

        static::assertSame($categoryIds, $this->getProductCategoryList($productId));
    }

    public function testUpdateRemovesObsoleteTreeRowsInANonLiveVersionContext(): void
    {
        $denormalizer = static::getContainer()->get(ProductCategoryDenormalizer::class);

        $versionId = Uuid::randomHex();
        $versionBytes = Uuid::fromHexToBytes($versionId);
        $liveBytes = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $productId = Uuid::randomHex();
        $productBytes = Uuid::fromHexToBytes($productId);
        $categoryBytes = Uuid::randomBytes();

        $this->connection->insert('category', [
            'id' => $categoryBytes,
            'version_id' => $liveBytes,
            'type' => 'page',
            'product_assignment_type' => 'product',
            'created_at' => '2000-01-01 00:00:00.000',
        ]);

        // product living in a draft version, without any category assignment
        $this->connection->insert('product', [
            'id' => $productBytes,
            'version_id' => $versionBytes,
            'categories' => $productBytes,
            'product_number' => 'denormalizer-version-' . $productId,
            'stock' => 1,
            'created_at' => '2000-01-01 00:00:00.000',
        ]);

        // tree row left over from an earlier indexing run; the category version is always the live one
        $this->connection->insert('product_category_tree', [
            'product_id' => $productBytes,
            'product_version_id' => $versionBytes,
            'category_id' => $categoryBytes,
            'category_version_id' => $liveBytes,
        ]);

        $denormalizer->update([$productId], $this->context->createWithVersionId($versionId));

        $remaining = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_category_tree WHERE product_id = :productId AND product_version_id = :versionId',
            ['productId' => $productBytes, 'versionId' => $versionBytes]
        );

        $this->connection->executeStatement('DELETE FROM product WHERE id = :id', ['id' => $productBytes]);
        $this->connection->executeStatement('DELETE FROM category WHERE id = :id', ['id' => $categoryBytes]);

        static::assertSame(0, (int) $remaining);
    }

    /**
     * @return array<string>|null
     */
    private function getProductCategoryList(string $productId): ?array
    {
        $productRepository = static::getContainer()->get('product.repository');
        /** @var ProductEntity $testableProduct */
        $testableProduct = $productRepository->search(new Criteria([$productId]), $this->context)->getEntities()->first();

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
