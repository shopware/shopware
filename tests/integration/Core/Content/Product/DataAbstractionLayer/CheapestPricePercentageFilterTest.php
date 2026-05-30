<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
class CheapestPricePercentageFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testProductWithoutListPriceIsExcludedByPercentageLessThan100(): void
    {
        $discountedId = Uuid::randomHex();
        $noListPriceId = Uuid::randomHex();

        $this->createProduct($discountedId, 90.0, 120.0);
        $this->createProduct($noListPriceId, 90.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('product.cheapestPrice.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discountedId, $result->getIds());
        static::assertNotContains($noListPriceId, $result->getIds());
    }

    public function testPercentageLessThanOrEqual100IncludesAllProductsWithListPrice(): void
    {
        $heavilyDiscounted = Uuid::randomHex();
        $slightlyDiscounted = Uuid::randomHex();
        $noDiscount = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createProduct($heavilyDiscounted, 50.0, 200.0);
        $this->createProduct($slightlyDiscounted, 95.0, 100.0);
        $this->createProduct($noDiscount, 100.0, 100.0);
        $this->createProduct($noListPrice, 100.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('product.cheapestPrice.percentage', [RangeFilter::LTE => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($heavilyDiscounted, $result->getIds());
        static::assertContains($slightlyDiscounted, $result->getIds());
        static::assertContains($noDiscount, $result->getIds(), 'percentage=100 should match LTE 100');
        static::assertNotContains($noListPrice, $result->getIds(), 'product without list price must not match');
    }

    public function testPercentageLessThan100ExcludesNonDiscountedProducts(): void
    {
        $discounted = Uuid::randomHex();
        $noDiscount = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createProduct($discounted, 90.0, 120.0);
        $this->createProduct($noDiscount, 100.0, 100.0);
        $this->createProduct($noListPrice, 100.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('product.cheapestPrice.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discounted, $result->getIds());
        static::assertNotContains($noDiscount, $result->getIds(), 'percentage=100 should not match LT 100');
        static::assertNotContains($noListPrice, $result->getIds(), 'product without list price must not match');
    }

    public function testPercentageRangeFilterWithBothBounds(): void
    {
        $discount25 = Uuid::randomHex();
        $discount50 = Uuid::randomHex();
        $discount75 = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createProduct($discount25, 75.0, 100.0);
        $this->createProduct($discount50, 50.0, 100.0);
        $this->createProduct($discount75, 25.0, 100.0);
        $this->createProduct($noListPrice, 50.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('product.cheapestPrice.percentage', [
            RangeFilter::GTE => 50,
            RangeFilter::LTE => 75,
        ]));

        $result = $this->searchIds($criteria);

        static::assertContains($discount25, $result->getIds(), 'percentage=75 should match GTE 50');
        static::assertContains($discount50, $result->getIds(), 'percentage=50 should match LTE 75');
        static::assertNotContains($discount75, $result->getIds(), 'percentage=25 is below lower bound');
        static::assertNotContains($noListPrice, $result->getIds(), 'product without list price should be excluded');
    }

    public function testPercentageSortingExcludesNullValues(): void
    {
        $discountedId = Uuid::randomHex();
        $noListPriceId = Uuid::randomHex();

        $this->createProduct($discountedId, 80.0, 100.0);
        $this->createProduct($noListPriceId, 80.0, null);

        $criteria = new Criteria([$discountedId, $noListPriceId]);
        $criteria->addSorting(new FieldSorting('product.cheapestPrice.percentage', FieldSorting::ASCENDING));
        $criteria->addFilter(new RangeFilter('product.cheapestPrice.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discountedId, $result->getIds());
        static::assertNotContains($noListPriceId, $result->getIds());
    }

    public function testPercentageEquals100MatchesNonDiscountedWithListPrice(): void
    {
        $nonDiscounted = Uuid::randomHex();
        $discounted = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createProduct($nonDiscounted, 100.0, 100.0);
        $this->createProduct($discounted, 90.0, 120.0);
        $this->createProduct($noListPrice, 100.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.cheapestPrice.percentage', 100));

        $result = $this->searchIds($criteria);

        static::assertContains($nonDiscounted, $result->getIds(), 'Product with price=listPrice (percentage=100) should match');
        static::assertNotContains($discounted, $result->getIds(), 'Discounted product should not match percentage=100');
        static::assertNotContains($noListPrice, $result->getIds(), 'Product without list price should not match percentage=100');
    }

    public function testPriceFieldPercentageFilterExcludesProductsWithoutListPrice(): void
    {
        $discountedId = Uuid::randomHex();
        $noListPriceId = Uuid::randomHex();

        $this->createProduct($discountedId, 90.0, 120.0);
        $this->createProduct($noListPriceId, 90.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('product.price.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discountedId, $result->getIds());
        static::assertNotContains($noListPriceId, $result->getIds());
    }

    private function searchIds(Criteria $criteria): IdSearchResult
    {
        return static::getContainer()->get('sales_channel.product.repository')
            ->searchIds($criteria, $this->salesChannelContext);
    }

    private function createProduct(string $id, float $priceGross, ?float $listGross): void
    {
        $price = [
            'currencyId' => Defaults::CURRENCY,
            'gross' => $priceGross,
            'net' => $priceGross,
            'linked' => false,
        ];

        if ($listGross !== null) {
            $price['listPrice'] = [
                'gross' => $listGross,
                'net' => $listGross,
                'linked' => false,
            ];
        }

        static::getContainer()->get('product.repository')->create([[
            'id' => $id,
            'productNumber' => $id,
            'name' => 'Percentage test ' . $id,
            'stock' => 10,
            'active' => true,
            'price' => [$price],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => 30,
                ],
            ],
        ]], $this->salesChannelContext->getContext());
    }
}
