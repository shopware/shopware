<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class InheritanceIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testManyToOneInheritanceUpdates(): void
    {
        $ids = new IdsCollection();

        $products = [
            [
                'id' => $ids->create('parent'),
                'name' => 'test',
                'manufacturer' => [
                    'id' => $ids->create('manufacturer'),
                    'name' => 'test',
                ],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false],
                ],
                'stock' => 10,
                'productNumber' => $ids->get('parent'),
            ],
            [
                'id' => $ids->create('variant-1'),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'productNumber' => $ids->get('variant-1'),
            ],
            [
                'id' => $ids->create('variant-2'),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'productNumber' => $ids->get('variant-2'),
            ],
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer'), $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), null, $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer'));

        // update variant-1 manufacturer, should update only variant-1
        $this->productRepository->update([
            [
                'id' => $ids->get('variant-1'),
                'manufacturer' => ['id' => $ids->create('manufacturer-2'), 'name' => 'test'],
            ],
        ], Context::createDefaultContext());

        $this->runWorker();

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer'), $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), $ids->get('manufacturer-2'), $ids->get('manufacturer-2'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer'));

        // update parent manufacturer - should update parent and variant-2
        $this->productRepository->update([
            [
                'id' => $ids->get('parent'),
                'manufacturer' => ['id' => $ids->create('manufacturer-3'), 'name' => 'test'],
            ],
        ], Context::createDefaultContext());
        $this->runWorker();

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer-3'), $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), $ids->get('manufacturer-2'), $ids->get('manufacturer-2'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer-3'));

        // reset variant-1 manufacturer - should update variant-1 with parent data
        $this->productRepository->update([
            ['id' => $ids->get('variant-1'), 'manufacturerId' => null],
        ], Context::createDefaultContext());
        $this->runWorker();

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer-3'), $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), null, $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer-3'));
    }

    public function testToManyInheritance(): void
    {
        $ids = new IdsCollection();

        $products = [
            [
                'id' => $ids->create('parent'),
                'name' => 'test',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false],
                ],
                'stock' => 10,
                'productNumber' => $ids->get('parent'),
                'prices' => [
                    [
                        'id' => $ids->create('parent-price'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false]],
                        'quantityStart' => 1,
                        'rule' => ['id' => $ids->create('rule'), 'name' => 'any', 'priority' => 1, 'payload' => ''],
                    ],
                ],
            ],
            [
                'id' => $ids->create('variant-1'),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'productNumber' => $ids->get('variant-1'),
            ],
            [
                'id' => $ids->create('variant-2'),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'productNumber' => $ids->get('variant-2'),
            ],
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        // test variants should inherit the parent price
        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), null, $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // add price to variant-1, variant-1 should now no more inherited
        $this->productRepository->update([
            [
                'id' => $ids->get('variant-1'),
                'prices' => [
                    [
                        'id' => $ids->create('variant-1-price'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false]],
                        'quantityStart' => 1,
                        'rule' => ['id' => $ids->create('rule'), 'name' => 'any', 'priority' => 1, 'payload' => ''],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // remove parent price
        static::getContainer()->get('product_price.repository')
            ->delete([
                ['id' => $ids->get('parent-price')],
            ], Context::createDefaultContext());

        $this->assertPriceInheritance($ids->get('parent'), null, $ids->get('parent'), false);
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'), false);

        // recreate price and remove variant price

        $this->productRepository->update([
            [
                'id' => $ids->get('parent'),
                'prices' => [
                    [
                        'id' => $ids->create('parent-price'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false]],
                        'quantityStart' => 1,
                        'rule' => ['id' => $ids->create('rule'), 'name' => 'any', 'priority' => 1, 'payload' => ''],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // remove variant price
        static::getContainer()->get('product_price.repository')
            ->delete([
                ['id' => $ids->get('variant-1-price')],
            ], Context::createDefaultContext());

        // test variants should inherit the parent price
        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), null, $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));
    }

    public function testManyToManyInheritance(): void
    {
        $ids = new IdsCollection();

        $products = [
            [
                'id' => $ids->create('parent'),
                'name' => 'test',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false],
                ],
                'stock' => 10,
                'productNumber' => $ids->get('parent'),
                'categories' => [
                    ['id' => $ids->create('parent-category'), 'name' => 'test'],
                ],
            ],
            [
                'id' => $ids->create('variant-1'),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'productNumber' => $ids->get('variant-1'),
            ],
            [
                'id' => $ids->create('variant-2'),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'productNumber' => $ids->get('variant-2'),
            ],
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        // test variants should inherit the parent categories
        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));

        // test assign own variant category
        $this->productRepository->update([
            [
                'id' => $ids->get('variant-1'),
                'categories' => [
                    ['id' => $ids->create('variant-1-category'), 'name' => 'test12'],
                ],
            ],
        ], Context::createDefaultContext());

        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('variant-1'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));

        static::getContainer()->get('product_category.repository')
            ->delete([
                ['productId' => $ids->get('variant-1'), 'categoryId' => $ids->get('variant-1-category')],
            ], Context::createDefaultContext());

        // test variants should inherit the parent categories
        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));
    }

    private function assertManufacturerInheritance(string $id, ?string $fk, ?string $association): void
    {
        $inheritance = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(product_manufacturer_id)) as fk, LOWER(HEX(manufacturer)) as association FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($inheritance);
        static::assertSame($fk, $inheritance['fk']);
        static::assertSame($association, $inheritance['association']);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('manufacturer');

        $product = $this->productRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertNotNull($product);
        static::assertSame($association, $product->getManufacturerId());
        static::assertNotNull($product->getManufacturer());
        static::assertSame($association, $product->getManufacturer()->getId());
    }

    private function assertPriceInheritance(string $id, ?string $fk, ?string $association, bool $hasPrices = true): void
    {
        $inheritance = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(prices)) as association FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($inheritance);
        static::assertSame($association, $inheritance['association']);

        $prices = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM product_price WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        ) ?: null;

        static::assertSame($fk, $prices);

        if (!$hasPrices) {
            return;
        }

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertNotNull($product);
        $prices = $product->getPrices();
        static::assertNotNull($prices);
        static::assertCount(1, $prices);
        static::assertNotNull($prices->first());
        static::assertSame($association, $prices->first()->getProductId());
    }

    private function assertCategoriesInheritance(string $id, ?string $association, bool $hasCategories = true): void
    {
        $inheritance = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(categories)) as association FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($inheritance);
        static::assertSame($association, $inheritance['association']);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        $product = $this->productRepository->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertNotNull($product);

        $count = $hasCategories ? 1 : 0;
        static::assertNotNull($product->getCategories());
        static::assertCount($count, $product->getCategories());
    }
}
