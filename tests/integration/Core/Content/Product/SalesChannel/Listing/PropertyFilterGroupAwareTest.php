<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * Regression test for https://github.com/shopware/shopware/issues/15812
 *
 * Verifies that property filter options are re-enabled correctly when
 * "Disable filter options without results" (reduce-aggregations) is active.
 *
 * Scenario:
 * - Product 1: color=tan + material=linen
 * - Product 2: color=gold + material=silk
 *
 * @internal
 */
#[Group('slow')]
class PropertyFilterGroupAwareTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->categoryId = $this->ids->create('category');

        $parent = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
        );

        static::getContainer()->get('category.repository')->create(
            [['id' => $this->categoryId, 'name' => 'group-aware-filter', 'parentId' => $parent]],
            Context::createDefaultContext()
        );

        $this->createProducts();
    }

    /**
     * Regression for https://github.com/shopware/shopware/issues/15812
     *
     * With only `linen` (material group) selected and `reduce-aggregations` enabled, the user
     * expects `silk` to remain selectable — clicking silk would OR-within-group with linen,
     * matching product 2 (gold+silk). Before the fix, the property filter constrained its own
     * aggregation, leaving `silk` disabled.
     */
    public function testSiblingOptionEnabledWhenOtherGroupSelectedAlone(): void
    {
        $available = $this->availableOptionIds($this->loadListing([
            'properties' => $this->ids->get('linen'),
            'reduce-aggregations' => '1',
            'only-aggregations' => '1',
        ]));

        static::assertContains($this->ids->get('linen'), $available, 'linen should be available (selected)');
        static::assertContains($this->ids->get('silk'), $available, 'silk should be available (OR-within-group with linen) — regression fix for #15812');
        static::assertContains($this->ids->get('tan'), $available, 'tan should be available (cross-group: tan+linen exists)');
        static::assertNotContains($this->ids->get('gold'), $available, 'gold should be disabled (no gold+linen product)');
    }

    /**
     * @param array<string, string> $params
     */
    private function loadListing(array $params): ProductListingResult
    {
        $request = new Request([], $params);
        $request->setMethod(Request::METHOD_POST);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        return static::getContainer()->get(ProductListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();
    }

    /**
     * @return list<string>
     */
    private function availableOptionIds(ProductListingResult $result): array
    {
        $aggregation = $result->getAggregations()->get('properties');
        static::assertInstanceOf(EntityResult::class, $aggregation);

        $groups = $aggregation->getEntities();
        static::assertInstanceOf(PropertyGroupCollection::class, $groups);

        $ids = [];
        foreach ($groups as $group) {
            foreach ($group->getOptions() ?? [] as $option) {
                $ids[] = $option->getId();
            }
        }

        return $ids;
    }

    private function createProducts(): void
    {
        static::getContainer()->get('property_group.repository')->create([
            [
                'id' => $this->ids->create('color'),
                'name' => 'color',
                'filterable' => true,
                'options' => [
                    ['id' => $this->ids->create('tan'), 'name' => 'tan'],
                    ['id' => $this->ids->create('gold'), 'name' => 'gold'],
                ],
            ],
            [
                'id' => $this->ids->create('material'),
                'name' => 'material',
                'filterable' => true,
                'options' => [
                    ['id' => $this->ids->create('linen'), 'name' => 'linen'],
                    ['id' => $this->ids->create('silk'), 'name' => 'silk'],
                ],
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get('product.repository')->create([
            $this->productPayload('product-1', [$this->ids->get('tan'), $this->ids->get('linen')]),
            $this->productPayload('product-2', [$this->ids->get('gold'), $this->ids->get('silk')]),
        ], Context::createDefaultContext());
    }

    /**
     * @param array<string> $propertyIds
     *
     * @return array<string, mixed>
     */
    private function productPayload(string $key, array $propertyIds): array
    {
        return [
            'id' => $this->ids->create($key),
            'productNumber' => $key,
            'name' => $key,
            'stock' => 10,
            'active' => true,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
            ],
            'tax' => ['name' => 'tax-' . $key, 'taxRate' => 19],
            'manufacturer' => ['name' => 'mf-' . $key],
            'categories' => [['id' => $this->categoryId]],
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'properties' => array_map(static fn (string $id) => ['id' => $id], $propertyIds),
        ];
    }
}
