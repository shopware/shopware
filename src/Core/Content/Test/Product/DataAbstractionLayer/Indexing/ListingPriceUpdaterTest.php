<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ListingPriceUpdater;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class ListingPriceUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testVariantsWithSimplePrices(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 2],
        ], $ids->context);

        $products = [
            [
                'id' => $ids->create('parent'),
                'stock' => 10,
                'name' => 'Simple 2',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                ],
            ],
            [
                // no prices & inherited price
                'id' => $ids->create('child-1'),
                'parentId' => $ids->get('parent'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                ],
            ],
            [
                // no prices & own price
                'id' => $ids->create('child-2'),
                'parentId' => $ids->get('parent'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 10, 'linked' => false],
                ],
            ],
            [
                // inherited price & own prices
                'id' => $ids->create('child-3'),
                'parentId' => $ids->get('parent'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 30, 'net' => 10, 'linked' => false],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->getContext());

        $context = new Context(new SystemSource(), [$ids->get('rule-a')], Defaults::CURRENCY);
        $context->addExtension('test', new Criteria());
        $context->setConsiderInheritance(true);

        $products = $this->getContainer()
            ->get('product.repository')
            ->search(new Criteria($ids->prefixed('child-')), $context);

        /** @var ProductEntity $child */
        $child = $products->get($ids->get('child-1'));
        static::assertInstanceOf(ProductEntity::class, $child);
        static::assertInstanceOf(ListingPriceCollection::class, $child->getListingPrices());

        $price = $child->getListingPrices()->getContextPrice($context);
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(30, $price->getTo()->getGross());

        $child = $products->get($ids->get('child-2'));
        static::assertInstanceOf(ListingPriceCollection::class, $child->getListingPrices());
        $price = $child->getListingPrices()->getContextPrice($context);
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(30, $price->getTo()->getGross());

        $child = $products->get($ids->get('child-3'));
        static::assertInstanceOf(ListingPriceCollection::class, $child->getListingPrices());
        $price = $child->getListingPrices()->getContextPrice($context);
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(30, $price->getTo()->getGross());
    }

    public function testMixedRuleAndSimplePrices(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 2],
        ], $ids->context);

        $products = [
            [
                'id' => $ids->create('parent'),
                'stock' => 10,
                'name' => 'Simple 2',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                ],
            ],
            [
                // no prices & inherited price
                'id' => $ids->create('child-1'),
                'parentId' => $ids->get('parent'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
            ],
            [
                // no prices & own price
                'id' => $ids->create('child-2'),
                'parentId' => $ids->get('parent'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 10, 'linked' => false],
                ],
            ],
            [
                // inherited price & own prices
                'id' => $ids->create('child-3'),
                'parentId' => $ids->get('parent'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'prices' => [
                    $this->formatPrice(100, Defaults::CURRENCY, $ids->get('rule-a'), 1, 4),
                    $this->formatPrice(44, Defaults::CURRENCY, $ids->get('rule-a'), 5),
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->getContext());

        $context = new Context(new SystemSource(), [$ids->get('rule-a')], Defaults::CURRENCY);
        $context->addExtension('test', new Criteria());
        $context->setConsiderInheritance(true);

        $products = $this->getContainer()
            ->get('product.repository')
            ->search(new Criteria($ids->prefixed('child-')), $context);

        /** @var ProductEntity $child */
        $child = $products->get($ids->get('child-1'));
        static::assertInstanceOf(ProductEntity::class, $child);
        static::assertInstanceOf(ListingPriceCollection::class, $child->getListingPrices());

        $price = $child->getListingPrices()->getContextPrice($context);
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(100, $price->getTo()->getGross());

        $child = $products->get($ids->get('child-2'));
        static::assertInstanceOf(ListingPriceCollection::class, $child->getListingPrices());
        $price = $child->getListingPrices()->getContextPrice($context);
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(100, $price->getTo()->getGross());

        $child = $products->get($ids->get('child-3'));
        static::assertInstanceOf(ListingPriceCollection::class, $child->getListingPrices());
        $price = $child->getListingPrices()->getContextPrice($context);
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(100, $price->getTo()->getGross());
    }

    public function testListingPriceWithDifferentCurrencies(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('currency.repository')->create([
            [
                'id' => $ids->create('dollar'),
                'name' => 'Dollar',
                'shortName' => 'DO',
                'symbol' => '$',
                'factor' => 2,
                'isoCode' => 'us',
                'decimalPrecision' => 2,
            ],
        ], $ids->context);

        $product = [
            'id' => $ids->create('product'),
            'stock' => 10,
            'name' => 'Simple 2',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'productNumber' => $ids->create('product'),
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 10, 'linked' => false],
                ['currencyId' => $ids->get('dollar'), 'gross' => 50, 'net' => 40, 'linked' => false],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $ids->getContext());

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$ids->get('product')]), $ids->getContext())
            ->first();

        static::assertCount(2, $product->getListingPrices());

        $price = $product->getListingPrices()->getContextPrice($ids->getContext());
        static::assertInstanceOf(ListingPrice::class, $price);
        static::assertEquals(20, $price->getFrom()->getGross());
        static::assertEquals(10, $price->getFrom()->getNet());

        $context = new Context(new SystemSource(), [], $ids->get('dollar'));
        $price = $product->getListingPrices()->getContextPrice($context);
        static::assertInstanceOf(ListingPrice::class, $price);
        static::assertEquals(50, $price->getFrom()->getGross());
        static::assertEquals(40, $price->getFrom()->getNet());

        $context = new Context(new SystemSource(), [], Uuid::randomHex());
        $price = $product->getListingPrices()->getContextPrice($context);
        static::assertInstanceOf(ListingPrice::class, $price);
        static::assertEquals(20, $price->getFrom()->getGross());
        static::assertEquals(10, $price->getFrom()->getNet());
    }

    public function testListingPriceWithNotUnifiedCurrencies(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 2],
        ], $ids->context);

        $this->getContainer()->get('currency.repository')->create([
            [
                'id' => $ids->create('dollar'),
                'name' => 'Dollar',
                'shortName' => 'DO',
                'symbol' => '$',
                'factor' => 2,
                'isoCode' => 'us',
                'decimalPrecision' => 2,
            ],
        ], $ids->context);

        $product = [
            'id' => $ids->create('product'),
            'stock' => 10,
            'name' => 'Simple 2',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'productNumber' => $ids->create('product'),
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 10, 'linked' => false],
            ],
            'prices' => [
                $this->formatPrices($ids->get('rule-a'), 1, 10, [Defaults::CURRENCY => 100, $ids->get('dollar') => 200]),

                // test: while indexing, this price will be calculated for dollar currency, each rule prices has to be the same currency base
                $this->formatPrices($ids->get('rule-a'), 11, null, [Defaults::CURRENCY => 50]),
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $ids->getContext());

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$ids->get('product')]), $ids->getContext())
            ->first();

        static::assertCount(3, $product->getListingPrices());

        $context = new Context(new SystemSource(), [$ids->get('rule-a')], Defaults::CURRENCY);
        $price = $product->getListingPrices()->getContextPrice($context);
        static::assertInstanceOf(ListingPrice::class, $price);
        static::assertEquals(50, $price->getFrom()->getGross());
        static::assertEquals(100, $price->getTo()->getGross());

        $context = new Context(new SystemSource(), [$ids->get('rule-a')], $ids->get('dollar'));
        $price = $product->getListingPrices()->getContextPrice($context);
        static::assertInstanceOf(ListingPrice::class, $price);
        static::assertEquals(100, $price->getFrom()->getGross());
        static::assertEquals(200, $price->getTo()->getGross());
    }

    public function testSortingWithDifferentCurrencies(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 2],
        ], $ids->context);

        $this->getContainer()->get('currency.repository')->create([
            [
                'id' => $ids->create('dollar'),
                'name' => 'Dollar',
                'shortName' => 'DO',
                'symbol' => '$',
                'factor' => 2,
                'isoCode' => 'us',
                'decimalPrecision' => 2,
            ],
        ], $ids->context);

        $products = [
            [
                'id' => $ids->create('product-1'),
                'stock' => 10,
                'name' => 'Simple 2',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 10, 'linked' => false],
                    // 40$ on demand calculation
                ],
                'prices' => [
                    $this->formatPrices($ids->get('rule-a'), 1, 10, [Defaults::CURRENCY => 100, $ids->get('dollar') => 120]),

                    // 40$ on demand calculation
                    $this->formatPrices($ids->get('rule-a'), 11, null, [Defaults::CURRENCY => 50]),
                ],
            ],
            [
                'id' => $ids->create('product-2'),
                'stock' => 10,
                'name' => 'Simple 2',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false],
                    ['currencyId' => $ids->get('dollar'), 'gross' => 10, 'net' => 10, 'linked' => false],
                ],
                'prices' => [
                    $this->formatPrices($ids->get('rule-a'), 1, 10, [Defaults::CURRENCY => 150, $ids->get('dollar') => 90]),
                    $this->formatPrices($ids->get('rule-a'), 11, null, [Defaults::CURRENCY => 75, $ids->get('dollar') => 80]),
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->getContext());

        $criteria = new Criteria($ids->getList(['product-1', 'product-2']));

        // rule-a & euro
        // product-1: 50-100€   |  product-2: 75-150€
        $this->assertSorting($criteria, [$ids->get('rule-a')], Defaults::CURRENCY, $ids->getList(['product-1', 'product-2']));

        // rule-a & dollar
        // product-1: 100-120$   |  product-2: 80-90$
        $this->assertSorting($criteria, [$ids->get('rule-a')], $ids->get('dollar'), $ids->getList(['product-2', 'product-1']), 2);

        // no-rule & euro
        // product-1: 20€   |  product-2: 100€
        $this->assertSorting($criteria, [], Defaults::CURRENCY, $ids->getList(['product-1', 'product-2']));

        // no-rule & dollar
        // product-1: 40$   |  product-2: 10$
        $this->assertSorting($criteria, [], $ids->get('dollar'), $ids->getList(['product-2', 'product-1']), 2);
    }

    public function testListingPriceSortingWithInheritance(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 1],
        ], $ids->context);

        $ids->context->setConsiderInheritance(true);

        $products = [
            [
                'id' => $ids->create('simple-1'),
                'stock' => 10,
                'name' => 'Simple product',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => $ids->get('simple-1'),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'prices' => [
                    $this->formatPrice(20, Defaults::CURRENCY, $ids->get('rule-a'), 1),
                ],
            ],
            [
                'id' => $ids->create('simple-2'),
                'stock' => 10,
                'name' => 'Simple 2',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => $ids->get('simple-2'),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 10, 'linked' => false]],
                'prices' => [
                    $this->formatPrice(10, Defaults::CURRENCY, $ids->get('rule-a'), 1),
                ],
            ],
            [
                'id' => $ids->create('parent'),
                'stock' => 10,
                'name' => 'price test',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => $ids->get('parent'),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 18, 'net' => 10, 'linked' => false]],
            ],
            [
                'id' => $ids->create('child'),
                'stock' => 10,
                'parentId' => $ids->get('parent'),
                'productNumber' => $ids->get('child'),
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->context);

        $criteria = new Criteria($ids->getList(['simple-1', 'simple-2', 'child']));
        $criteria->addSorting(new FieldSorting('product.listingPrices'));

        // test for simple prices fallback
        /** @var EntitySearchResult $listing */
        $listing = $this->getContainer()->get('product.repository')
            ->search($criteria, $ids->context);

        $sorted = array_values($listing->getIds());

        static::assertEquals(
            [$ids->get('simple-1'), $ids->get('child'), $ids->get('simple-2')],
            $sorted
        );

        // test for advanced price rules
        $ids->context->setRuleIds([$ids->get('rule-a')]);

        /** @var EntitySearchResult $listing */
        $listing = $this->getContainer()->get('product.repository')
            ->search($criteria, $ids->context);

        $sorted = array_values($listing->getIds());

        static::assertEquals(
            [$ids->get('simple-2'), $ids->get('child'), $ids->get('simple-1')],
            $sorted
        );
    }

    public function testPriceUpdateConsideredInListingPriceIndexer(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();
        $ruleC = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleC, 'name' => 'test', 'priority' => 1],
        ], $context);

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                $this->formatPrice(15, Defaults::CURRENCY, $ruleA, 1, 10, 10.0, $id),
                $this->formatPrice(10, Defaults::CURRENCY, $ruleA, 11, 20),
                $this->formatPrice(5, Defaults::CURRENCY, $ruleA, 21, null),

                $this->formatPrice(20, Defaults::CURRENCY, $ruleB, 1, 10),
                $this->formatPrice(15, Defaults::CURRENCY, $ruleB, 11, null),

                $this->formatPrice(10, Defaults::CURRENCY, $ruleC, 1, 10),
                $this->formatPrice(5, Defaults::CURRENCY, $ruleC, 11, null),
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], $context);

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$id]), $context)
            ->get($id);

        $prices = $product->getListingPrices();

        static::assertInstanceOf(ListingPriceCollection::class, $prices);
        static::assertCount(4, $prices, 'Onyl one price per rule and one default price should be generated');

        $aPrices = $this->filterByRuleId($prices, $ruleA);
        $aPrices = $this->filterByCurrencyId($aPrices, Defaults::CURRENCY);

        static::assertCount(1, $aPrices);

        /** @var ListingPrice $aPrice */
        $aPrice = $aPrices[0];

        static::assertSame(5.0, $aPrice->getFrom()->getGross());
        static::assertSame(15.0, $aPrice->getTo()->getGross());

        $update = [
            'id' => $id,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 30, 'net' => 1, 'linked' => false]],
        ];

        $this->getContainer()->get('product_price.repository')
            ->update([$update], $context);

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$id]), $context)
            ->get($id);

        $prices = $product->getListingPrices();

        static::assertInstanceOf(ListingPriceCollection::class, $prices);
        static::assertCount(4, $prices);

        $aPrices = $this->filterByRuleId($prices, $ruleA);
        $aPrices = $this->filterByCurrencyId($aPrices, Defaults::CURRENCY);

        static::assertCount(1, $aPrices);

        /** @var ListingPrice $aPrice */
        $aPrice = $aPrices[0];

        static::assertSame(5.0, $aPrice->getFrom()->getGross());
        static::assertSame(30.0, $aPrice->getTo()->getGross());
        static::assertTrue($aPrice->isDifferent());
    }

    public function testListingPriceWithoutVariants(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleA,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
                    ],
                ],
                [
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false],
                    ],
                ],
                [
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $products = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext());

        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        $price = $this->filterByCurrencyId(
            $this->filterByRuleId($product->getListingPrices(), $ruleA),
            Defaults::CURRENCY
        );

        static::assertCount(1, $price);
        $price = $price[0];

        /** @var ListingPrice $price */
        static::assertSame(10.0, $price->getFrom()->getGross());
    }

    public function testDeletePrices(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 1],
        ], Context::createDefaultContext());

        $data = [
            'id' => $ids->create('product'),
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ids->create('price-1'),
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ids->get('rule-a'),
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
                    ],
                ],
                [
                    'id' => $ids->create('price-2'),
                    'quantityStart' => 21,
                    'ruleId' => $ids->get('rule-a'),
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], $ids->getContext());

        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$ids->get('product')]), $ids->getContext())
            ->get($ids->get('product'));

        static::assertInstanceOf(ProductEntity::class, $product);

        $prices = $product->getListingPrices();

        static::assertInstanceOf(ListingPriceCollection::class, $prices);

        $prices = $this->filterByRuleId($prices, $ids->get('rule-a'));
        $prices = $this->filterByCurrencyId($prices, Defaults::CURRENCY);

        static::assertCount(1, $prices);
        $price = array_shift($prices);

        /** @var ListingPrice $price */
        static::assertEquals(10, $price->getFrom()->getGross());
        static::assertEquals(100, $price->getTo()->getGross());

        $this->getContainer()->get('product_price.repository')
            ->delete([
                ['id' => $ids->get('price-2')],
            ], $ids->getContext());

        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$ids->get('product')]), $ids->getContext())
            ->get($ids->get('product'));

        static::assertInstanceOf(ProductEntity::class, $product);

        $prices = $product->getListingPrices();

        static::assertInstanceOf(ListingPriceCollection::class, $prices);

        static::assertCount(2, $prices);

        $prices = $this->filterByRuleId($prices, $ids->get('rule-a'));
        $prices = $this->filterByCurrencyId($prices, Defaults::CURRENCY);

        static::assertCount(1, $prices);
        $price = array_shift($prices);

        /** @var ListingPrice $price */
        static::assertEquals(100, $price->getFrom()->getGross());
        static::assertEquals(100, $price->getTo()->getGross());

        $this->getContainer()->get('product_price.repository')
            ->delete([
                ['id' => $ids->get('price-1')],
            ], $ids->getContext());

        $product = $this->getContainer()->get('product.repository')
            ->search(new Criteria([$ids->get('product')]), $ids->getContext())
            ->get($ids->get('product'));

        static::assertInstanceOf(ProductEntity::class, $product);

        $prices = $product->getListingPrices();

        static::assertInstanceOf(ListingPriceCollection::class, $prices);
        static::assertEquals(1, $prices->count());
    }

    public function testListingPriceUpdatesWithStringPrices(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $this->getContainer()->get('product.repository')->create([
            [
                'id' => $ids->create('product'),
                'stock' => 10,
                'name' => 'Simple 2',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10.5, 'net' => 10.5, 'linked' => false],
                ],
            ],
        ], $ids->getContext());

        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);

        $price = $connection->fetchColumn('SELECT price FROM product WHERE id = UNHEX(?)', [
            $ids->get('product'),
        ]);

        $decodedPrice = json_decode($price, true);

        // Check Serializer did their job
        static::assertIsFloat($decodedPrice['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['net']);
        static::assertIsFloat($decodedPrice['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['gross']);

        // Let's break it to see ListingPriceUpdater takes care about that
        $decodedPrice['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['net'] = (string) $decodedPrice['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['net'];
        $decodedPrice['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['gross'] = (string) $decodedPrice['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['gross'];

        $connection->executeUpdate('UPDATE product SET price = ? WHERE id = UNHEX(?)', [
            json_encode($decodedPrice),
            $ids->get('product'),
        ]);

        $this->getContainer()->get(ListingPriceUpdater::class)->update([$ids->get('product')], $ids->getContext());

        $listingPrices = json_decode($connection->fetchColumn('SELECT listing_prices FROM product WHERE id = UNHEX(?)', [
            $ids->get('product'),
        ]), true);

        static::assertIsFloat($listingPrices['default']['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['from']['net']);
        static::assertIsFloat($listingPrices['default']['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['from']['gross']);
        static::assertIsFloat($listingPrices['default']['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['to']['net']);
        static::assertIsFloat($listingPrices['default']['cb7d2554b0ce847cd82f3ac9bd1c0dfca']['to']['gross']);
    }

    private function filterByCurrencyId(iterable $prices, string $currencyId): array
    {
        $filtered = [];
        /** @var ListingPrice $price */
        foreach ($prices as $price) {
            if ($price->getCurrencyId() === $currencyId) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function filterByRuleId(iterable $prices, string $ruleId): array
    {
        $filtered = [];
        /** @var ListingPrice $price */
        foreach ($prices as $price) {
            if ($price->getRuleId() === $ruleId) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function formatPrice(
        float $gross,
        string $currencyId,
        string $ruleId,
        int $quantityStart,
        ?int $quantityEnd = null,
        ?float $net = null,
        ?string $id = null
    ): array {
        $id = $id ?? Uuid::randomHex();

        return [
            'id' => $id,
            'quantityStart' => $quantityStart,
            'quantityEnd' => $quantityEnd,
            'ruleId' => $ruleId,
            'price' => [
                [
                    'currencyId' => $currencyId,
                    'gross' => $gross,
                    'net' => $net ?? $gross / 1.19,
                    'linked' => false,
                ],
            ],
        ];
    }

    private function formatPrices(string $ruleId, int $start, ?int $end, array $prices): array
    {
        $formatted = [];
        foreach ($prices as $currencyId => $price) {
            $formatted[] = [
                'currencyId' => $currencyId,
                'gross' => $price,
                'net' => $price / 1.19,
                'linked' => false,
            ];
        }

        return [
            'quantityStart' => $start,
            'quantityEnd' => $end,
            'ruleId' => $ruleId,
            'price' => $formatted,
        ];
    }

    private function assertSorting(Criteria $original, array $ruleIds, string $currencyId, array $expected, int $factor = 1): void
    {
        $criteria = clone $original;
        $criteria->addSorting(new FieldSorting('product.listingPrices'));

        $context = new Context(new SystemSource(), $ruleIds, $currencyId, [Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, $factor);
        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $context);

        static::assertEquals(array_values($expected), array_values($result->getIds()));

        $criteria = clone $original;
        $criteria->addSorting(new FieldSorting('product.listingPrices', FieldSorting::DESCENDING));

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $context);

        static::assertEquals(array_reverse(array_values($expected)), array_values($result->getIds()));
    }
}
