<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\Aggregation\Bucketing\CompositeAggregation;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser
 */
class CriteriaParserTest extends TestCase
{
    public function testAggregationWithSorting(): void
    {
        $aggs = new TermsAggregation('foo', 'test', null, new FieldSorting('abc', FieldSorting::ASCENDING), new TermsAggregation('foo', 'foo2'));

        $definition = $this->getDefinition();

        /** @var CompositeAggregation $esAgg */
        $esAgg = (new CriteriaParser(new EntityDefinitionQueryHelper()))->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(CompositeAggregation::class, $esAgg);
        static::assertSame([
            'composite' => [
                'sources' => [
                    [
                        'foo.sorting' => [
                            'terms' => [
                                'field' => 'abc',
                                'order' => 'ASC',
                            ],
                        ],
                    ],
                    [
                        'foo.key' => [
                            'terms' => [
                                'field' => 'test',
                            ],
                        ],
                    ],
                ],
                'size' => 10000,
            ],
            'aggregations' => [
                'foo' => [
                    'terms' => [
                        'field' => 'foo2',
                        'size' => 10000,
                    ],
                ],
            ],
        ], $esAgg->toArray());
    }

    /**
     * @dataProvider accessorContextProvider
     */
    public function testBuildAccessor(string $field, Context $context, string $expectedAccessor): void
    {
        $definition = $this->getDefinition();

        $accessor = (new CriteriaParser(new EntityDefinitionQueryHelper()))->buildAccessor($definition, $field, $context);

        static::assertSame($expectedAccessor, $accessor);
    }

    /**
     * @return iterable<string, array{string, Context, string}>
     */
    public function accessorContextProvider(): iterable
    {
        yield 'normal field' => [
            'foo',
            Context::createDefaultContext(),
            'foo',
        ];

        yield 'price, state from field: gross' => [
            'price.foo.gross',
            Context::createDefaultContext(),
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.gross',
        ];

        yield 'price, state from field: net' => [
            'price.foo.net',
            Context::createDefaultContext(),
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.net',
        ];

        yield 'price, state inherited from context: gross' => [
            'price.foo',
            Context::createDefaultContext(),
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.gross',
        ];

        $stateNet = Context::createDefaultContext();
        $stateNet->setTaxState(CartPrice::TAX_STATE_NET);

        yield 'price, state inherited from context: net' => [
            'price.foo',
            $stateNet,
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.net',
        ];
    }

    /**
     * @dataProvider providerCheapestPrice
     *
     * @param array<mixed> $expectedQuery
     */
    public function testCheapestPriceSorting(FieldSorting $sorting, array $expectedQuery, Context $context): void
    {
        $definition = $this->getDefinition();

        $sorting = (new CriteriaParser(new EntityDefinitionQueryHelper()))->parseSorting($sorting, $definition, $context);

        $script = $sorting->getParameter('script');

        static::assertSame($expectedQuery, $script);
    }

    /**
     * @return iterable<string, array{FieldSorting, array<mixed>, Context}>
     */
    public function providerCheapestPrice(): iterable
    {
        yield 'default cheapest price' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => true,
                    'multiplier' => 100.0,
                ],
            ],
            Context::createDefaultContext(),
        ];

        yield 'default cheapest price/list price percentage' => [
            new FieldSorting('cheapestPrice.percentage', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price_percentage',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                            'factor' => 1,
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext(),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['currencyId' => 'foo']);

        yield 'different currency cheapest price' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyfoo_gross',
                            'factor' => 1,
                        ],
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1.0,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => true,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];

        yield 'different currency cheapest price/list price percentage' => [
            new FieldSorting('cheapestPrice.percentage', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price_percentage',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyfoo_gross_percentage',
                            'factor' => 1,
                        ],
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                            'factor' => 1.0,
                        ],
                    ],
                ],
            ],
            $context,
        ];

        $context = Context::createDefaultContext();
        $context->getRounding()->setDecimals(3);

        yield 'default cheapest price: rounding with 3 decimals' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 1000,
                    'round' => false,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];

        $context = Context::createDefaultContext();
        $context->assign(['taxState' => CartPrice::TAX_STATE_NET]);
        $context->getRounding()->setRoundForNet(false);

        yield 'default cheapest price: net rounding disabled' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_net',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => false,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];
    }

    /**
     * @dataProvider providerFilter
     *
     * @param array<mixed> $expectedFilter
     */
    public function testFilterParsing(Filter $filter, array $expectedFilter): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->getDefinition();

        $sortedFilter = (new CriteriaParser(new EntityDefinitionQueryHelper()))->parseFilter($filter, $definition, '', $context);

        static::assertEquals($expectedFilter, $sortedFilter->toArray());
    }

    /**
     * @return iterable<string, array{Filter, array<mixed>}>
     */
    public function providerFilter(): iterable
    {
        yield 'not filter: and' => [
            new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'test' => 'value',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'test2' => 'value',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'not filter: or' => [
            new NotFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'term' => [
                                            'test' => 'value',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'test2' => 'value',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'not filter: xor' => [
            new NotFilter(MultiFilter::CONNECTION_XOR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'bool' => [
                                            'must' => [
                                                [
                                                    'term' => [
                                                        'test' => 'value',
                                                    ],
                                                ],
                                            ],
                                            'must_not' => [
                                                [
                                                    'term' => [
                                                        'test2' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'bool' => [
                                            'must_not' => [
                                                [
                                                    'term' => [
                                                        'test' => 'value',
                                                    ],
                                                ],
                                            ],
                                            'must' => [
                                                [
                                                    'term' => [
                                                        'test2' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'range filter: cheapestPrice' => [
            new RangeFilter('cheapestPrice', [RangeFilter::GTE => 10]),
            [
                'script' => [
                    'script' => [
                        'id' => 'cheapest_price_filter',
                        'params' => [
                            RangeFilter::GTE => 10,
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                                    'factor' => 1,
                                ],
                            ],
                            'decimals' => 100,
                            'round' => true,
                            'multiplier' => 100.0,
                        ],
                    ],
                ],
            ],
        ];

        yield 'range filter: cheapestPrice price/list price percentage' => [
            new RangeFilter('cheapestPrice.percentage', [RangeFilter::GTE => 10]),
            [
                'script' => [
                    'script' => [
                        'id' => 'cheapest_price_percentage_filter',
                        'params' => [
                            RangeFilter::GTE => 10,
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                                    'factor' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getDefinition(): EntityDefinition
    {
        $instanceRegistry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $instanceRegistry->getByEntityName('product');
    }
}
