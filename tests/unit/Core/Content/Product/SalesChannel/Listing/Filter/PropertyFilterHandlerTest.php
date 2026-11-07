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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PropertyListingFilterHandler::class)]
class PropertyFilterHandlerTest extends TestCase
{
    public function testDeactivateFilter(): void
    {
        $request = new Request([], ['property-filter' => false]);
        $request->setMethod(Request::METHOD_POST);
        $context = static::createStub(SalesChannelContext::class);
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
        $context = static::createStub(SalesChannelContext::class);
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
     * @param array<string, array<string>> $expectedGroupedOptions group id => list of option ids in that group
     */
    #[DataProvider('createProvider')]
    public function testCreate(array $input, AndFilter $expectedFilter, array $expectedIds, array $mapping, array $expectedGroupedOptions): void
    {
        $request = new Request([], ['properties' => implode('|', $input)]);

        $request->setMethod(Request::METHOD_POST);

        $context = static::createStub(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($mapping);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        // Without `reduce-aggregations` the handler returns simple TermsAggregations and
        // exclude=false, mirroring the legacy behaviour. The post-filter still encodes per-group
        // OR semantics so the listing result itself is filtered correctly.
        $expected = new Filter(
            'properties',
            true,
            [
                new TermsAggregation('properties', 'product.properties.id'),
                new TermsAggregation('options', 'product.options.id'),
            ],
            $expectedFilter,
            $expectedIds,
            false
        );

        static::assertEquals($expected, $result);
    }

    /**
     * @param array<string> $input
     * @param array<string> $expectedIds
     * @param array<array<string, string>> $mapping
     * @param array<string, array<string>> $expectedGroupedOptions group id => list of option ids in that group
     */
    #[DataProvider('createProvider')]
    public function testCreateWithReduceAggregationsUsesGroupAwareAggregations(
        array $input,
        AndFilter $expectedFilter,
        array $expectedIds,
        array $mapping,
        array $expectedGroupedOptions
    ): void {
        $request = new Request([], [
            'properties' => implode('|', $input),
            'reduce-aggregations' => '1',
        ]);

        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($mapping);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            true,
            self::expectedGroupAwareAggregations($expectedGroupedOptions),
            $expectedFilter,
            $expectedIds,
            true
        );

        static::assertEquals($expected, $result);
    }

    public function testCreateWithInvalidIds(): void
    {
        $request = new Request([], ['properties' => 'foo|bar']);

        $request->setMethod(Request::METHOD_POST);

        $context = static::createStub(SalesChannelContext::class);

        $connection = static::createStub(Connection::class);

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

        $context = static::createStub(SalesChannelContext::class);
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

        $languageInfo = new LanguageInfo(Generator::LANGUAGE_INFO_NAME, Generator::LANGUAGE_INFO_LOCALE_CODE);
        $context = Generator::generateSalesChannelContext(languageInfo: $languageInfo);

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
            static::createStub(Connection::class)
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

    public function testProcessCollectsIdsFromPerGroupAggregations(): void
    {
        // Simulates the post-DBAL state of a reduce-aggregations request where the per-group
        // aggregation surfaced an option (silk) that the base aggregation did not. The processor
        // must merge ids from both base and per-group results.
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var StaticEntityRepository<PropertyGroupCollection> $groupRepository */
        $groupRepository = new StaticEntityRepository([
            static fn (Criteria $criteria) => new PropertyGroupCollection([
                (new PropertyGroupEntity())->assign([
                    'id' => 'material',
                    'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
                    'position' => 1,
                ]),
            ]),
        ], new PropertyGroupDefinition());

        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) {
                static::assertContains('linen', $criteria->getIds());
                static::assertContains('silk', $criteria->getIds());

                return new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'linen',
                        'groupId' => 'material',
                        'position' => 1,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'silk',
                        'groupId' => 'material',
                        'position' => 2,
                    ]),
                ]);
            },
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
                // Base aggregation: properties filter is applied → only 'linen' (no silk because
                // no product has material=silk AND matches the current property filter).
                new TermsResult('properties', [
                    new Bucket('linen', 1, null),
                ]),
                new TermsResult('options', [
                    new Bucket('linen', 1, null),
                ]),
                // Per-group aggregation: lifts the material group's selection → 'silk' shows up.
                new TermsResult('properties.material', [
                    new Bucket('linen', 1, null),
                    new Bucket('silk', 1, null),
                ]),
                new TermsResult('options.material', [
                    new Bucket('linen', 1, null),
                    new Bucket('silk', 1, null),
                ]),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $handler->process($request, $result, $context);

        // All per-group aggregations cleaned up.
        static::assertFalse($result->getAggregations()->has('properties.material'));
        static::assertFalse($result->getAggregations()->has('options.material'));
        static::assertFalse($result->getAggregations()->has('options'));

        $properties = $result->getAggregations()->get('properties');
        static::assertInstanceOf(EntityResult::class, $properties);

        $material = $properties->getEntities()->first();
        static::assertInstanceOf(Entity::class, $material);
        static::assertSame('material', $material->get('id'));

        $options = $material->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options, 'silk should re-enable via the per-group aggregation');

        $ids = [];
        foreach ($options as $option) {
            $ids[] = $option->get('id');
        }
        static::assertContains('linen', $ids);
        static::assertContains('silk', $ids);
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

            // expected grouped options for per-group aggregations
            [
                $ids->get('size') => [$ids->get('XL')],
                $ids->get('color') => [$ids->get('green')],
            ],
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

            // expected grouped options for per-group aggregations
            [
                $ids->get('color') => [$ids->get('green'), $ids->get('red')],
            ],
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

            // expected grouped options for per-group aggregations
            [
                $ids->get('color') => [$ids->get('green'), $ids->get('red')],
                $ids->get('size') => [$ids->get('XL'), $ids->get('L')],
            ],
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

            // expected grouped options for per-group aggregations
            [
                $ids->get('size') => [$ids->get('XL')],
                $ids->get('color') => [$ids->get('green')],
            ],
        ];
    }

    /**
     * @param array<string, array<string>> $groupedOptions
     *
     * @return list<Aggregation>
     */
    private static function expectedGroupAwareAggregations(array $groupedOptions): array
    {
        $groupOrFilters = [];
        foreach ($groupedOptions as $groupId => $optionIds) {
            $groupOrFilters[$groupId] = new OrFilter([
                new EqualsAnyFilter('product.optionIds', $optionIds),
                new EqualsAnyFilter('product.propertyIds', $optionIds),
            ]);
        }

        $allGroupFilters = array_values($groupOrFilters);

        $aggregations = [
            new FilterAggregation(
                'properties-base',
                new TermsAggregation('properties', 'product.properties.id'),
                $allGroupFilters
            ),
            new FilterAggregation(
                'options-base',
                new TermsAggregation('options', 'product.options.id'),
                $allGroupFilters
            ),
        ];

        foreach ($groupOrFilters as $groupId => $_) {
            $otherGroupFilters = $groupOrFilters;
            unset($otherGroupFilters[$groupId]);
            $otherGroupFilters = array_values($otherGroupFilters);

            $aggregations[] = new FilterAggregation(
                'properties-group-' . $groupId,
                new TermsAggregation('properties.' . $groupId, 'product.properties.id'),
                [
                    new EqualsFilter('product.properties.groupId', $groupId),
                    ...$otherGroupFilters,
                ]
            );

            $aggregations[] = new FilterAggregation(
                'options-group-' . $groupId,
                new TermsAggregation('options.' . $groupId, 'product.options.id'),
                [
                    new EqualsFilter('product.options.groupId', $groupId),
                    ...$otherGroupFilters,
                ]
            );
        }

        return $aggregations;
    }

    private function getHandlerWithConnection(Connection $connection): PropertyListingFilterHandler
    {
        $groupRepository = new StaticEntityRepository([], new PropertyGroupDefinition());
        $optionRepository = new StaticEntityRepository([], new PropertyGroupOptionDefinition());

        return new PropertyListingFilterHandler(
            $groupRepository,
            $optionRepository,
            $connection
        );
    }
}
