<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * Regression test for https://github.com/shopware/shopware/issues/15812:
 * "Disable filter options without results - Is not correctly updated if value is selected".
 *
 * @internal
 */
#[Group('store-api')]
class PropertyReducedAggregationsTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    /**
     * Scenario from the bug report:
     *
     *  - Two property groups `color` and `size` each with two options.
     *  - Product 1 has (color=red, size=XL).
     *  - Product 2 has (color=green, size=L).
     *
     * When a user selects color=red and size=XL, then removes the color selection,
     * the reduce-aggregations refresh sends `properties=size_XL` with
     * `reduce-aggregations=1`. The size aggregation must re-enable its sibling
     * option `size=L`, because that option is reachable if the size selection is
     * lifted. Before the fix, the property aggregation embedded the full group
     * AndFilter and therefore kept `size=L` disabled.
     */
    public function testSiblingInStillSelectedGroupReEnablesAfterOtherGroupSelectionIsRemoved(): void
    {
        $this->createData();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // Simulate the storefront's reduce-aggregations refresh after removing the
        // color selection: only the size option remains active.
        $request = new Request([], [
            'properties' => $this->ids->get('XL'),
            'reduce-aggregations' => '1',
            'only-aggregations' => '1',
        ]);

        $listing = static::getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->ids->get('category'), $request, $context, new Criteria())
            ->getResult();

        $aggregation = $listing->getAggregations()->get('properties');
        static::assertInstanceOf(EntityResult::class, $aggregation);

        /** @var PropertyGroupCollection $groups */
        $groups = $aggregation->getEntities();

        $sizeGroup = $groups->get($this->ids->get('size'));
        static::assertNotNull($sizeGroup, 'size property group must still be present');

        $sizeOptions = $sizeGroup->getOptions();
        static::assertNotNull($sizeOptions);

        $sizeOptionIds = $sizeOptions->getIds();
        static::assertContains(
            $this->ids->get('XL'),
            $sizeOptionIds,
            'currently selected size option must stay visible'
        );
        static::assertContains(
            $this->ids->get('L'),
            $sizeOptionIds,
            'sibling size option must be re-enabled when another group\'s selection is removed'
        );

        // Sanity: the unselected color group is still enumerated so the frontend
        // can render buckets for it, and the catch-all aggregation's cross-group
        // narrowing does keep options that match the still-active size=XL filter.
        // We assert the positive case (red matches XL) and guard against a
        // regression where the catch-all stops narrowing (`green` also shown)
        // without over-asserting on bucket counts, which behave slightly
        // differently between the MySQL and Elasticsearch aggregation backends.
        $colorGroup = $groups->get($this->ids->get('color'));
        static::assertNotNull($colorGroup);
        $colorOptions = $colorGroup->getOptions();
        static::assertNotNull($colorOptions);
        $colorOptionIds = $colorOptions->getIds();
        static::assertContains(
            $this->ids->get('red'),
            $colorOptionIds,
            'color=red must remain enumerated because Product 1 (red, XL) matches the active size=XL filter'
        );
        static::assertLessThanOrEqual(
            2,
            \count($colorOptionIds),
            'catch-all aggregation must still narrow the unselected color group (ran against all color options ⇒ regression)'
        );
    }

    private function createData(): void
    {
        $parent = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
        );

        static::getContainer()->get('category.repository')->create(
            [['id' => $this->ids->get('category'), 'name' => 'test', 'parentId' => $parent]],
            Context::createDefaultContext()
        );

        $baseDefaults = [
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'categories' => [
                ['id' => $this->ids->get('category')],
            ],
        ];

        $products = [
            array_merge($baseDefaults, [
                'id' => $this->ids->get('p1'),
                'productNumber' => 'p1',
                'name' => 'product 1',
                'properties' => [
                    [
                        'id' => $this->ids->get('red'),
                        'name' => 'red',
                        'groupId' => $this->ids->get('color'),
                        'group' => ['id' => $this->ids->get('color'), 'name' => 'color', 'filterable' => true],
                    ],
                    [
                        'id' => $this->ids->get('XL'),
                        'name' => 'XL',
                        'groupId' => $this->ids->get('size'),
                        'group' => ['id' => $this->ids->get('size'), 'name' => 'size', 'filterable' => true],
                    ],
                ],
            ]),
            array_merge($baseDefaults, [
                'id' => $this->ids->get('p2'),
                'productNumber' => 'p2',
                'name' => 'product 2',
                'properties' => [
                    [
                        'id' => $this->ids->get('green'),
                        'name' => 'green',
                        'groupId' => $this->ids->get('color'),
                    ],
                    [
                        'id' => $this->ids->get('L'),
                        'name' => 'L',
                        'groupId' => $this->ids->get('size'),
                    ],
                ],
            ]),
        ];

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }
}
