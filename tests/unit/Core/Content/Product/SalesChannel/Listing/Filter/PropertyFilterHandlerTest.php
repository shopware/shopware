<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PropertyListingFilterHandler::class)]
class PropertyFilterHandlerTest extends TestCase
{
    public function testDeactivateFilter(): void
    {
        $request = new Request([], ['property-filter' => false]);
        $request->setMethod(Request::METHOD_POST);
        $context = $this->createMock(SalesChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        static::assertNull($result);
    }

    public function testEmptyRequest(): void
    {
        $request = new Request([], ['properties' => '']);
        $request->setMethod(Request::METHOD_POST);
        $context = $this->createMock(SalesChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            false,
            [
                new TermsAggregation('properties', 'product.properties.id'),
                new TermsAggregation('options', 'product.options.id'),
            ],
            new AndFilter(),
            [],
            false
        );

        static::assertEquals($expected, $result);
    }

    /**
     * @param array<string> $input
     * @param array<string> $expectedIds
     * @param array<array<string, string>> $mapping
     * @param array<string> $expectedGroupIds
     */
    #[DataProvider('createProvider')]
    public function testCreate(
        array $input,
        AndFilter $expectedFilter,
        array $expectedIds,
        array $mapping,
        array $expectedGroupIds
    ): void {
        $request = new Request([], ['properties' => implode('|', $input)]);

        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($mapping);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        static::assertInstanceOf(Filter::class, $result);
        static::assertSame('properties', $result->getName());
        static::assertTrue($result->isFiltered());
        static::assertTrue($result->exclude());
        static::assertEquals($expectedFilter, $result->getFilter());
        static::assertSame($expectedIds, $result->getValues());

        $this->assertGroupAwareAggregations($result->getAggregations(), $expectedGroupIds);
    }

    public function testCreateWithInvalidIds(): void
    {
        $request = new Request([], ['properties' => 'foo|bar']);

        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            false,
            [
                new TermsAggregation('properties', 'product.properties.id'),
                new TermsAggregation('options', 'product.options.id'),
            ],
            new AndFilter([]),
            [],
            false
        );

        static::assertEquals($expected, $result);
    }

    public function testPreFilteredGroups(): void
    {
        $request = new Request([], [PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM => ['color', 'size']]);
        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            false,
            [
                new FilterAggregation(
                    'properties-filter',
                    new TermsAggregation('properties', 'product.properties.id'),
                    [new EqualsAnyFilter('product.properties.groupId', ['color', 'size'])]
                ),
                new FilterAggregation(
                    'options-filter',
                    new TermsAggregation('options', 'product.options.id'),
                    [new EqualsAnyFilter('product.options.groupId', ['color', 'size'])],
                ),
            ],
            new AndFilter(),
            [],
            false
        );

        static::assertEquals($expected, $result);
    }

    public function testProcess(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var StaticEntityRepository<PropertyGroupCollection> $groupRepository */
        $groupRepository = new StaticEntityRepository([
            static function (Criteria $criteria) {
                static::assertContains('color', $criteria->getIds());
                static::assertContains('size', $criteria->getIds());

                return new PropertyGroupCollection([
                    (new PropertyGroupEntity())->assign([
                        'id' => 'color',
                        'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
                        'position' => 1,
                    ]),
                    (new PropertyGroupEntity())->assign([
                        'id' => 'size',
                        'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
                        'position' => 2,
                    ]),
                ]);
            },
            new PropertyGroupCollection(),
        ], new PropertyGroupDefinition());

        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) {
                static::assertContains('red', $criteria->getIds());
                static::assertContains('green', $criteria->getIds());
                static::assertContains('xl', $criteria->getIds());
                static::assertContains('l', $criteria->getIds());

                return new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'red',
                        'groupId' => 'color',
                        'position' => 1,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'green',
                        'groupId' => 'color',
                        'position' => 2,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'xl',
                        'groupId' => 'size',
                        'position' => 2,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'l',
                        'groupId' => 'size',
                        'position' => 1,
                    ]),
                ]);
            },
            new PropertyGroupOptionCollection(),
        ], new PropertyGroupOptionDefinition());

        $handler = new PropertyListingFilterHandler(
            $groupRepository,
            $repository,
            $this->createMock(Connection::class)
        );

        $result = new ProductListingResult(
            'test',
            1,
            new ProductCollection(),
            new AggregationResultCollection([
                new TermsResult('properties', [
                    new Bucket('red', 1, null),
                    new Bucket('green', 1, null),
                ]),
                new TermsResult('options', [
                    new Bucket('xl', 1, null),
                    new Bucket('l', 1, null),
                ]),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $handler->process($request, $result, $context);

        static::assertTrue($result->getAggregations()->has('properties'));
        static::assertFalse($result->getAggregations()->has('options'));

        $properties = $result->getAggregations()->get('properties');

        static::assertInstanceOf(EntityResult::class, $properties);
        static::assertCount(2, $properties->getEntities());

        $color = $properties->getEntities()->first();
        static::assertInstanceOf(Entity::class, $color);
        static::assertSame('color', $color->get('id'));

        $options = $color->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options);

        static::assertInstanceOf(Entity::class, $options->first());
        static::assertSame('red', $options->first()->get('id'));
        static::assertInstanceOf(Entity::class, $options->last());
        static::assertSame('green', $options->last()->get('id'));

        $size = $properties->getEntities()->last();
        static::assertInstanceOf(Entity::class, $size);
        static::assertSame('size', $size->get('id'));

        $options = $size->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options);
        static::assertInstanceOf(Entity::class, $options->first());
        static::assertSame('l', $options->first()->get('id'));
        static::assertInstanceOf(Entity::class, $options->last());
        static::assertSame('xl', $options->last()->get('id'));
    }

    /**
     * Regression test for https://github.com/shopware/shopware/issues/15812.
     *
     * Ensures that the aggregations produced for a multi-group selection do NOT embed the
     * filter of the group that they aggregate over. Otherwise the `reduce-aggregations`
     * recomputation would keep sibling options of a still-selected property group disabled
     * after another group's selection is removed.
     */
    public function testGroupAwareExclusionDoesNotConstrainOwnGroup(): void
    {
        $ids = new IdsCollection();

        $request = new Request([], [
            'properties' => implode('|', [$ids->get('red'), $ids->get('XL')]),
        ]);
        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
            ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
        ]);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);
        static::assertInstanceOf(Filter::class, $result);

        // The main post-filter must still include BOTH group filters so the listing itself
        // stays fully narrowed.
        static::assertEquals(
            new AndFilter([
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('red')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('red')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('XL')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('XL')]),
                ]),
            ]),
            $result->getFilter()
        );

        // Exclude must be true so the AggregationListingProcessor does not re-add the
        // combined property filter on top of our group-aware aggregations.
        static::assertTrue($result->exclude());

        $aggregations = [];
        foreach ($result->getAggregations() as $aggregation) {
            static::assertInstanceOf(FilterAggregation::class, $aggregation);
            $aggregations[$aggregation->getName()] = $aggregation;
        }

        // color-aware aggregation must apply size-filter but NOT color-filter.
        $colorAggregation = $aggregations['properties-' . $ids->get('color') . '-filter'] ?? null;
        static::assertInstanceOf(FilterAggregation::class, $colorAggregation);

        $colorFilters = $colorAggregation->getFilter();
        static::assertEquals(new EqualsFilter('product.properties.groupId', $ids->get('color')), $colorFilters[0]);

        $colorSelfFilter = new OrFilter([
            new EqualsAnyFilter('product.optionIds', [$ids->get('red')]),
            new EqualsAnyFilter('product.propertyIds', [$ids->get('red')]),
        ]);
        foreach (\array_slice($colorFilters, 1) as $embedded) {
            static::assertNotEquals($colorSelfFilter, $embedded, 'color aggregation must not constrain the color group');
        }

        // The size-filter *must* be present (cross-group narrowing stays active).
        $sizeOtherFilter = new OrFilter([
            new EqualsAnyFilter('product.optionIds', [$ids->get('XL')]),
            new EqualsAnyFilter('product.propertyIds', [$ids->get('XL')]),
        ]);
        static::assertTrue(
            $this->containsFilter(\array_slice($colorFilters, 1), $sizeOtherFilter),
            'color aggregation must keep the size group filter'
        );

        // size-aware aggregation: symmetric check.
        $sizeAggregation = $aggregations['properties-' . $ids->get('size') . '-filter'] ?? null;
        static::assertInstanceOf(FilterAggregation::class, $sizeAggregation);

        $sizeFilters = $sizeAggregation->getFilter();
        static::assertEquals(new EqualsFilter('product.properties.groupId', $ids->get('size')), $sizeFilters[0]);
        foreach (\array_slice($sizeFilters, 1) as $embedded) {
            static::assertNotEquals($sizeOtherFilter, $embedded, 'size aggregation must not constrain the size group');
        }
        static::assertTrue(
            $this->containsFilter(\array_slice($sizeFilters, 1), $colorSelfFilter),
            'size aggregation must keep the color group filter'
        );

        // Catch-all aggregation for unselected groups must scope NOT IN selected groups and
        // apply every selected filter.
        $catchAll = $aggregations['properties-filter'] ?? null;
        static::assertInstanceOf(FilterAggregation::class, $catchAll);
        static::assertInstanceOf(NotFilter::class, $catchAll->getFilter()[0]);
        static::assertTrue($this->containsFilter($catchAll->getFilter(), $colorSelfFilter));
        static::assertTrue($this->containsFilter($catchAll->getFilter(), $sizeOtherFilter));
    }

    public static function createProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test two groups and single option' => [
            // input for the request
            [$ids->get('XL'), $ids->get('green')],

            // expected filter
            new AndFilter([
                // each "group" should be an OR filter (e.g. size OR color)
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('XL')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('XL')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('green')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('green')]),
                ]),
            ]),

            // expected ids
            [$ids->get('XL'), $ids->get('green')],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
            ],

            // expected group ids (in selection/mapping order)
            [$ids->get('size'), $ids->get('color')],
        ];

        yield 'Test with single group and multiple options' => [
            [$ids->get('green'), $ids->get('red')],

            // expected filter
            new AndFilter([
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('green'), $ids->get('red')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('green'), $ids->get('red')]),
                ]),
            ]),

            // expected ids
            [$ids->get('green'), $ids->get('red')],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
            ],

            // expected group ids
            [$ids->get('color')],
        ];

        yield 'Test with multiple groups and multiple options' => [
            [
                $ids->get('green'),
                $ids->get('red'),
                $ids->get('XL'),
                $ids->get('L'),
            ],

            // expected filter
            new AndFilter([
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('green'), $ids->get('red')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('green'), $ids->get('red')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('XL'), $ids->get('L')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('XL'), $ids->get('L')]),
                ]),
            ]),

            // expected ids
            [
                $ids->get('green'),
                $ids->get('red'),
                $ids->get('XL'),
                $ids->get('L'),
            ],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('L')],
            ],

            // expected group ids
            [$ids->get('color'), $ids->get('size')],
        ];

        yield 'Test two groups and single option with invalid id' => [
            // input for the request
            [$ids->get('XL'), $ids->get('green'), 'foo', 'bar'],

            // expected filter
            new AndFilter([
                // each "group" should be an OR filter (e.g. size OR color)
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('XL')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('XL')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('product.optionIds', [$ids->get('green')]),
                    new EqualsAnyFilter('product.propertyIds', [$ids->get('green')]),
                ]),
            ]),

            // expected ids
            [$ids->get('XL'), $ids->get('green')],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
            ],

            // expected group ids
            [$ids->get('size'), $ids->get('color')],
        ];
    }

    /**
     * @param array<int, Aggregation> $aggregations
     * @param array<string> $expectedGroupIds
     */
    private function assertGroupAwareAggregations(array $aggregations, array $expectedGroupIds): void
    {
        static::assertCount(\count($expectedGroupIds) * 2 + 2, $aggregations, 'Expected per-group + catch-all aggregations');

        $byName = [];
        foreach ($aggregations as $aggregation) {
            static::assertInstanceOf(FilterAggregation::class, $aggregation);
            $byName[$aggregation->getName()] = $aggregation;
        }

        foreach ($expectedGroupIds as $groupId) {
            $propertyKey = 'properties-' . $groupId . '-filter';
            $optionKey = 'options-' . $groupId . '-filter';

            static::assertArrayHasKey($propertyKey, $byName);
            static::assertArrayHasKey($optionKey, $byName);

            $propertyAggregation = $byName[$propertyKey];

            $innerProperty = $propertyAggregation->getAggregation();
            static::assertInstanceOf(TermsAggregation::class, $innerProperty);
            static::assertSame('properties-' . $groupId, $innerProperty->getName());

            // The first filter must be the groupId scope.
            $propertyFilters = $propertyAggregation->getFilter();
            static::assertNotEmpty($propertyFilters);
            static::assertEquals(
                new EqualsFilter('product.properties.groupId', $groupId),
                $propertyFilters[0]
            );
        }

        // Catch-all aggregations (for groups without a selection) must carry a NotFilter
        // scoping to groups other than the selected ones, plus all selected group filters.
        static::assertArrayHasKey('properties-filter', $byName);
        static::assertArrayHasKey('options-filter', $byName);

        $catchProperty = $byName['properties-filter'];
        $catchFilters = $catchProperty->getFilter();
        static::assertInstanceOf(NotFilter::class, $catchFilters[0]);
        static::assertCount(\count($expectedGroupIds) + 1, $catchFilters);
    }

    /**
     * @param array<\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter> $haystack
     */
    private function containsFilter(array $haystack, \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter $needle): bool
    {
        $serializedNeedle = serialize($needle);
        foreach ($haystack as $filter) {
            if (serialize($filter) === $serializedNeedle) {
                return true;
            }
        }

        return false;
    }

    private function getHandlerWithConnection(Connection $connection): PropertyListingFilterHandler
    {
        /** @var StaticEntityRepository<PropertyGroupCollection> $groupRepository */
        $groupRepository = new StaticEntityRepository([], new PropertyGroupDefinition());
        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = new StaticEntityRepository([], new PropertyGroupOptionDefinition());

        return new PropertyListingFilterHandler(
            $groupRepository,
            $optionRepository,
            $connection
        );
    }
}
