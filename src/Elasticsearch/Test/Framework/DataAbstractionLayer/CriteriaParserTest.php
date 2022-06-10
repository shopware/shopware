<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Framework\DataAbstractionLayer;

use ONGR\ElasticsearchDSL\Aggregation\Bucketing\CompositeAggregation;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;

/**
 * @internal
 */
class CriteriaParserTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAggregationWithSorting(): void
    {
        $aggs = new TermsAggregation('foo', 'test', null, new FieldSorting('abc', FieldSorting::ASCENDING), new TermsAggregation('foo', 'foo2'));

        $definition = $this->getContainer()->get(ProductDefinition::class);

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
        $definition = $this->getContainer()->get(ProductDefinition::class);

        $accessor = (new CriteriaParser(new EntityDefinitionQueryHelper()))->buildAccessor($definition, $field, $context);

        static::assertSame($expectedAccessor, $accessor);
    }

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
     */
    public function testCheapestPriceSorting(FieldSorting $sorting, array $expectedQuery, Context $context): void
    {
        $definition = $this->getContainer()->get(ProductDefinition::class);

        $sorting = (new CriteriaParser(new EntityDefinitionQueryHelper()))->parseSorting($sorting, $definition, $context);

        $script = $sorting->getParameter('script');

        static::assertSame($expectedQuery, $script);
    }

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
     */
    public function testFilterParsing(Filter $filter, array $expectedFilter): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->getContainer()->get(ProductDefinition::class);

        $sortedFilter = (new CriteriaParser(new EntityDefinitionQueryHelper()))->parseFilter($filter, $definition, '', $context);

        static::assertEquals($expectedFilter, $sortedFilter->toArray());
    }

    public function providerFilter(): iterable
    {
        yield 'not filter: and' => [
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
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
            new NotFilter(NotFilter::CONNECTION_OR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
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
            new NotFilter(NotFilter::CONNECTION_XOR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
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
}
