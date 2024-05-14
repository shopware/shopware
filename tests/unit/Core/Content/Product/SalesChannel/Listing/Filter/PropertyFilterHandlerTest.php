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
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

        $connection->expects(static::never())
            ->method('fetchAllAssociative');

        $handler = new PropertyListingFilterHandler(
            new StaticEntityRepository([]),
            $connection
        );

        $result = $handler->create($request, $context);

        static::assertNull($result);
    }

    public function testEmptyRequest(): void
    {
        $request = new Request([], ['properties' => '']);
        $request->setMethod(Request::METHOD_POST);
        $context = $this->createMock(SalesChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects(static::never())
            ->method('fetchAllAssociative');

        $handler = new PropertyListingFilterHandler(
            new StaticEntityRepository([]),
            $connection
        );

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
     * @param array<array<string, string>> $mapping
     */
    #[DataProvider('createProvider')]
    public function testCreate(array $input, AndFilter $expected, array $mapping): void
    {
        $request = new Request([], ['properties' => implode('|', $input)]);

        $request->setMethod(Request::METHOD_POST);

        $context = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);

        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn($mapping);

        $handler = new PropertyListingFilterHandler(
            new StaticEntityRepository([]),
            $connection
        );

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            true,
            [
                new TermsAggregation('properties', 'product.properties.id'),
                new TermsAggregation('options', 'product.options.id'),
            ],
            $expected,
            $input,
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

        $connection->expects(static::never())
            ->method('fetchAllAssociative');

        $handler = new PropertyListingFilterHandler(
            new StaticEntityRepository([]),
            $connection
        );

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

        new StaticDefinitionInstanceRegistry(
            [$definition = new PropertyGroupOptionDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $repository = new StaticEntityRepository([
            function (Criteria $criteria) {
                static::assertContains('red', $criteria->getIds());
                static::assertContains('green', $criteria->getIds());
                static::assertContains('xl', $criteria->getIds());
                static::assertContains('l', $criteria->getIds());

                return new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'red',
                        'groupId' => 'color',
                        'position' => 1,
                        'group' => (new PropertyGroupEntity())->assign([
                            'id' => 'color',
                            'position' => 1,
                        ]),
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'green',
                        'groupId' => 'color',
                        'position' => 2,
                        'group' => (new PropertyGroupEntity())->assign([
                            'id' => 'color',
                            'position' => 2,
                        ]),
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'xl',
                        'groupId' => 'size',
                        'position' => 2,
                        'group' => (new PropertyGroupEntity())->assign([
                            'id' => 'size',
                            'position' => 1,
                        ]),
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'l',
                        'groupId' => 'size',
                        'position' => 1,
                        'group' => (new PropertyGroupEntity())->assign([
                            'id' => 'size',
                            'position' => 1,
                        ]),
                    ]),
                ]);
            },
            new PropertyGroupOptionCollection(),
        ], $definition);

        $handler = new PropertyListingFilterHandler($repository, $this->createMock(Connection::class));

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
        static::assertEquals('color', $color->get('id'));

        $options = $color->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options);

        static::assertInstanceOf(Entity::class, $options->first());
        static::assertEquals('red', $options->first()->get('id'));
        static::assertInstanceOf(Entity::class, $options->last());
        static::assertEquals('green', $options->last()->get('id'));

        $size = $properties->getEntities()->last();
        static::assertInstanceOf(Entity::class, $size);
        static::assertEquals('size', $size->get('id'));

        $options = $size->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options);
        static::assertInstanceOf(Entity::class, $options->first());
        static::assertEquals('l', $options->first()->get('id'));
        static::assertInstanceOf(Entity::class, $options->last());
        static::assertEquals('xl', $options->last()->get('id'));
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

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
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

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
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

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('L')],
            ],
        ];
    }
}
