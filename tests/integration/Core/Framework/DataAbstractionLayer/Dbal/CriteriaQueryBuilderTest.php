<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\ResolveCriteriaProductListingRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CriteriaQueryBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public IdsCollection $ids;

    /**
     * This test checks listing sorting behavior affected by MySQL's GROUP BY handling.
     *
     * Shopware disables ONLY_FULL_GROUP_BY, allowing queries that may return non-deterministic
     * results when selecting columns not functionally dependent on the GROUP BY clause.
     * See: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html
     *
     * The CriteriaQueryBuilder generates such a query. Without the fix, results may vary between
     * runs; with the fix, the outcome is deterministic and the test should always pass.
     *
     * A specific ID set is used to reproduce the issue. For reference, the following ID set
     * does NOT trigger the problem and is noted here for completeness:
     * ['s1' => '00000000000000000000000000000001',
     *  's1.1' => '00000000000000000000000000000002',
     *  's1.2' => '00000000000000000000000000000003',
     *  'p1' => '00000000000000000000000000000004',
     *  'p1.1' => '00000000000000000000000000000005',
     *  'p1.2' => '00000000000000000000000000000006']
     *
     * Changing the data may prevent the issue from appearing, so edit with caution.
     */
    public function testSortingByCheapestPrice(): void
    {
        $this->ids = new IdsCollection();
        $this->createExampleProducts();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::SALES_CHANNEL
        );

        static::assertSame(
            [
                $this->ids->get('p1'),
                $this->ids->get('s1'),
            ],
            array_values($this->orderListing('price-asc', $context))
        );

        static::assertSame(
            [
                $this->ids->get('s1'),
                $this->ids->get('p1'),
            ],
            array_values($this->orderListing('price-desc', $context))
        );
    }

    public function testSortingByPriceUsesDisplayedParentPrice(): void
    {
        $this->ids = new IdsCollection();
        $this->createPriceSortingProducts();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::SALES_CHANNEL
        );

        static::assertSame(
            [
                $this->ids->get('product-a'),
                $this->ids->get('product-b'),
                $this->ids->get('product-c'),
            ],
            array_values($this->orderListing('price-asc', $context))
        );

        static::assertSame(
            [
                $this->ids->get('product-c'),
                $this->ids->get('product-b'),
                $this->ids->get('product-a'),
            ],
            array_values($this->orderListing('price-desc', $context))
        );
    }

    public function testSortingByNameUsesDisplayedParentName(): void
    {
        $this->ids = new IdsCollection();
        $this->createNameSortingProducts();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::SALES_CHANNEL
        );

        static::assertSame(
            [
                $this->ids->get('name-a'),
                $this->ids->get('name-b'),
                $this->ids->get('name-c'),
            ],
            array_values($this->orderListing('name-asc', $context))
        );

        static::assertSame(
            [
                $this->ids->get('name-c'),
                $this->ids->get('name-b'),
                $this->ids->get('name-a'),
            ],
            array_values($this->orderListing('name-desc', $context))
        );
    }

    private function createExampleProducts(): void
    {
        $this->ids->set('s1', '0198bd43b1c37964a5c1ecbd2d89fd6e');
        $this->ids->set('s1.1', '0198c286060673308abe19bf59ccb004');
        $this->ids->set('s1.2', '0198c286209972e68646a54bf8211144');
        $this->ids->set('p1', '0198bd4417f471a69742ca2390243653');
        $this->ids->set('p1.1', '0198bd446e0973448206e3197b2d24ea');
        $this->ids->set('p1.2', '0198bd446e077084984f95175b2bea27');

        $s1 = (new ProductBuilder($this->ids, 's1'))
            ->price(200)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility()
            ->variant(
                (new ProductBuilder($this->ids, 's1.1'))
                    ->price(110)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 's1.2'))
                    ->price(120)
                    ->build()
            )
            ->build();
        $p1 = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility()
            ->variant(
                (new ProductBuilder($this->ids, 'p1.1'))
                    ->price(50)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'p1.2'))
                    ->price(200)
                    ->build()
            )
            ->build();

        static::getContainer()->get('product.repository')->create([$s1, $p1], Context::createDefaultContext());
    }

    private function createPriceSortingProducts(): void
    {
        $products = [
            $this->buildDisplayParentProduct(
                key: 'product-a',
                name: 'Product A',
                price: 100.0,
                variants: [
                    ['key' => 'product-a.1', 'name' => 'Variant Z Product A', 'price' => 110.0],
                    ['key' => 'product-a.2', 'name' => 'Variant ZZ Product A', 'price' => 120.0],
                ]
            ),
            $this->buildDisplayParentProduct(
                key: 'product-b',
                name: 'Product B',
                price: 200.0,
                variants: [
                    ['key' => 'product-b.1', 'name' => 'Cheap Variant Product B', 'price' => 50.0],
                    ['key' => 'product-b.2', 'name' => 'Premium Variant Product B', 'price' => 500.0],
                ]
            ),
            $this->buildDisplayParentProduct(
                key: 'product-c',
                name: 'Product C',
                price: 300.0,
                variants: [
                    ['key' => 'product-c.1', 'name' => 'Variant Z Product C', 'price' => 310.0],
                    ['key' => 'product-c.2', 'name' => 'Variant ZZ Product C', 'price' => 320.0],
                ]
            ),
        ];

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }

    private function createNameSortingProducts(): void
    {
        $products = [
            $this->buildDisplayParentProduct(
                key: 'name-a',
                name: 'A',
                price: 100.0,
                variants: [
                    ['key' => 'name-a.1', 'name' => 'ZZZ A', 'price' => 110.0],
                    ['key' => 'name-a.2', 'name' => 'YYY A', 'price' => 120.0],
                ]
            ),
            $this->buildDisplayParentProduct(
                key: 'name-b',
                name: 'B',
                price: 200.0,
                variants: [
                    ['key' => 'name-b.1', 'name' => 'ZZZ B', 'price' => 210.0],
                    ['key' => 'name-b.2', 'name' => 'YYY B', 'price' => 220.0],
                ]
            ),
            $this->buildDisplayParentProduct(
                key: 'name-c',
                name: 'C',
                price: 300.0,
                variants: [
                    ['key' => 'name-c.1', 'name' => '100 C', 'price' => 310.0],
                    ['key' => 'name-c.2', 'name' => 'ZZZ C', 'price' => 320.0],
                ]
            ),
        ];

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }

    /**
     * @param list<array{key: string, name: string, price: float}> $variants
     */
    private function buildDisplayParentProduct(string $key, string $name, float $price, array $variants): array
    {
        $product = (new ProductBuilder($this->ids, $key))
            ->name($name)
            ->price($price)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility();

        foreach ($variants as $variant) {
            $product->variant(
                (new ProductBuilder($this->ids, $variant['key']))
                    ->name($variant['name'])
                    ->price($variant['price'])
                    ->build()
            );
        }

        return $product->build();
    }

    /**
     * @return string[]
     */
    private function orderListing(string $dir, SalesChannelContext $context): array
    {
        $result = static::getContainer()->get(ResolveCriteriaProductListingRoute::class)->load(
            $this->ids->get('test-category'),
            new Request(query: ['order' => $dir]),
            $context,
            new Criteria()
        );

        return $result->getResult()->getEntities()->getIds();
    }
}
