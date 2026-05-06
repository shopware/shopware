<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\ResolveCriteriaProductListingRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\CustomField\CustomFieldTypes;
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

    public function testSortingByCustomFieldUsesDisplayedParentValue(): void
    {
        $this->ids = new IdsCollection();
        $this->createCustomFieldSortingProducts();
        $this->createProductCustomFieldSorting('custom-field-asc', 'asc');
        $this->createProductCustomFieldSorting('custom-field-desc', 'desc');

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::SALES_CHANNEL
        );

        static::assertSame(
            [
                $this->ids->get('custom-a'),
                $this->ids->get('custom-b'),
                $this->ids->get('custom-c'),
                $this->ids->get('custom-d'),
            ],
            array_values($this->orderListing('custom-field-asc', $context))
        );

        static::assertSame(
            [
                $this->ids->get('custom-d'),
                $this->ids->get('custom-c'),
                $this->ids->get('custom-b'),
                $this->ids->get('custom-a'),
            ],
            array_values($this->orderListing('custom-field-desc', $context))
        );
    }

    public function testSortingByCustomFieldDoesNotRequireParentJoinWithoutInheritance(): void
    {
        $this->ids = new IdsCollection();
        $this->createCustomFieldSortingProducts();
        $this->createProductCustomFieldSorting('custom-field-asc', 'asc');

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::SALES_CHANNEL
        );
        $context->getContext()->setConsiderInheritance(false);

        static::assertIsArray($this->orderListing('custom-field-asc', $context));
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
            ->price(100)
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

    private function createCustomFieldSortingProducts(): void
    {
        static::getContainer()->get('custom_field_set.repository')->create([
            [
                'id' => $this->ids->create('custom-field-set'),
                'name' => 'listing_sort_custom_field_set',
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name' => 'listing_sort_weight',
                        'type' => CustomFieldTypes::FLOAT,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $products = [
            $this->buildDisplayParentCustomFieldProduct(
                key: 'custom-a',
                name: 'Custom A',
                parentValue: 100.0,
                variants: [
                    ['key' => 'custom-a.1', 'name' => 'Variant A 1', 'price' => 110.0, 'value' => null],
                    ['key' => 'custom-a.2', 'name' => 'Variant A 2', 'price' => 120.0, 'value' => null],
                ]
            ),
            $this->buildDisplayParentCustomFieldProduct(
                key: 'custom-b',
                name: 'Custom B',
                parentValue: 200.0,
                variants: [
                    ['key' => 'custom-b.1', 'name' => 'Variant B 1', 'price' => 210.0, 'value' => 999.0],
                    ['key' => 'custom-b.2', 'name' => 'Variant B 2', 'price' => 220.0, 'value' => null],
                ]
            ),
            $this->buildDisplayParentCustomFieldProduct(
                key: 'custom-c',
                name: 'Custom C',
                parentValue: 250.0,
                variants: [
                    ['key' => 'custom-c.1', 'name' => 'Variant C 1', 'price' => 260.0, 'value' => 10.0],
                    ['key' => 'custom-c.2', 'name' => 'Variant C 2', 'price' => 270.0, 'value' => null],
                ]
            ),
            $this->buildDisplayParentCustomFieldProduct(
                key: 'custom-d',
                name: 'Custom D',
                parentValue: 300.0,
                variants: [
                    ['key' => 'custom-d.1', 'name' => 'Variant D 1', 'price' => 310.0, 'value' => null],
                    ['key' => 'custom-d.2', 'name' => 'Variant D 2', 'price' => 320.0, 'value' => null],
                ]
            ),
        ];

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }

    private function createProductCustomFieldSorting(string $key, string $direction): void
    {
        static::getContainer()->get('product_sorting.repository')->create([
            [
                'id' => $this->ids->create('sorting-' . $key),
                'key' => $key,
                'priority' => 1,
                'active' => true,
                'fields' => [
                    [
                        'field' => 'customFields.listing_sort_weight',
                        'order' => $direction,
                        'priority' => 1,
                        'naturalSorting' => false,
                    ],
                ],
                'label' => 'Sort by listing_sort_weight ' . $direction,
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @param list<array{key: string, name: string, price: float, value: float|null}> $variants
     *
     * @return array<mixed>
     */
    private function buildDisplayParentCustomFieldProduct(string $key, string $name, float $parentValue, array $variants): array
    {
        $product = (new ProductBuilder($this->ids, $key))
            ->name($name)
            ->price($parentValue)
            ->customField('listing_sort_weight', $parentValue)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility();

        foreach ($variants as $variant) {
            $builder = (new ProductBuilder($this->ids, $variant['key']))
                ->name($variant['name'])
                ->price($variant['price']);

            if ($variant['value'] !== null) {
                $builder->customField('listing_sort_weight', $variant['value']);
            }

            $product->variant($builder->build());
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
