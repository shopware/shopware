<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class InheritanceIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testManyToOneInheritanceUpdates(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

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

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->context);

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer'), $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), null, $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer'));

        // update variant-1 manufacturer, should update only variant-1
        $this->getContainer()->get('product.repository')
            ->update([
                [
                    'id' => $ids->get('variant-1'),
                    'manufacturer' => ['id' => $ids->create('manufacturer-2'), 'name' => 'test'],
                ],
            ], $ids->context);

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer'), $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), $ids->get('manufacturer-2'), $ids->get('manufacturer-2'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer'));

        // update parent manufacturer - should update parent and variant-2
        $this->getContainer()->get('product.repository')
            ->update([
                [
                    'id' => $ids->get('parent'),
                    'manufacturer' => ['id' => $ids->create('manufacturer-3'), 'name' => 'test'],
                ],
            ], $ids->context);

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer-3'), $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), $ids->get('manufacturer-2'), $ids->get('manufacturer-2'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer-3'));

        // reset variant-1 manufacturer - should update variant-1 with parent data
        $this->getContainer()->get('product.repository')
            ->update([
                ['id' => $ids->get('variant-1'), 'manufacturerId' => null],
            ], $ids->context);

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer-3'), $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), null, $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer-3'));
    }

    public function testToManyInheritance(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

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

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->context);

        // test variants should inherit the parent price
        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), null, $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // add price to variant-1, variant-1 should now no more inherited
        $this->getContainer()->get('product.repository')
            ->update([
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
            ], $ids->context);

        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // remove parent price
        $this->getContainer()->get('product_price.repository')
            ->delete([
                ['id' => $ids->get('parent-price')],
            ], $ids->context);

        $this->assertPriceInheritance($ids->get('parent'), null, $ids->get('parent'), false);
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'), false);

        // recreate price and remove variant price

        $this->getContainer()->get('product.repository')
            ->update([
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
            ], $ids->context);

        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // remove variant price
        $this->getContainer()->get('product_price.repository')
            ->delete([
                ['id' => $ids->get('variant-1-price')],
            ], $ids->context);

        // test variants should inherit the parent price
        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), null, $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));
    }

    public function testManyToManyInheritance(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

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

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->context);

        // test variants should inherit the parent categories
        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));

        // test assign own variant category
        $this->getContainer()->get('product.repository')
            ->update([
                [
                    'id' => $ids->get('variant-1'),
                    'categories' => [
                        ['id' => $ids->create('variant-1-category'), 'name' => 'test12'],
                    ],
                ],
            ], $ids->context);

        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('variant-1'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));

        $this->getContainer()->get('product_category.repository')
            ->delete([
                ['productId' => $ids->get('variant-1'), 'categoryId' => $ids->get('variant-1-category')],
            ], $ids->context);

        // test variants should inherit the parent categories
        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));
    }

    private function assertManufacturerInheritance(string $id, ?string $fk, ?string $association): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $inheritance = $connection->fetchAssoc(
            'SELECT LOWER(HEX(product_manufacturer_id)) as fk, LOWER(HEX(manufacturer)) as association FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertEquals($fk, $inheritance['fk']);
        static::assertEquals($association, $inheritance['association']);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('manufacturer');

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search($criteria, $context)
            ->get($id);

        static::assertEquals($association, $product->getManufacturerId());
        static::assertEquals($association, $product->getManufacturer()->getId());
    }

    private function assertPriceInheritance(string $id, ?string $fk, ?string $association, bool $hasPrices = true): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $inheritance = $connection->fetchAssoc(
            'SELECT LOWER(HEX(prices)) as association FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertEquals($association, $inheritance['association']);

        $prices = $connection->fetchColumn(
            'SELECT LOWER(HEX(id)) FROM product_price WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertEquals($fk, $prices);

        if (!$hasPrices) {
            return;
        }

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search($criteria, $context)
            ->get($id);

        static::assertCount(1, $product->getPrices());
        static::assertEquals($association, $product->getPrices()->first()->getProductId());
    }

    private function assertCategoriesInheritance(string $id, ?string $association, bool $hasCategories = true): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $inheritance = $connection->fetchAssoc(
            'SELECT LOWER(HEX(categories)) as association FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertEquals($association, $inheritance['association']);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search($criteria, $context)
            ->get($id);

        $count = $hasCategories ? 1 : 0;
        static::assertCount($count, $product->getCategories());
    }
}
