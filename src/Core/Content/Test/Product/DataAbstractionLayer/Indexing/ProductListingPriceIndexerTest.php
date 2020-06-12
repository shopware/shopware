<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductListingPriceIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

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
        static::assertCount(27, $prices);

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
        static::assertCount(27, $prices);

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
        static::assertEquals(0, $prices->count());
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
}
