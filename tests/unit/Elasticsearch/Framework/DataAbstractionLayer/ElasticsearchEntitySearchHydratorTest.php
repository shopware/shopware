<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearchHydrator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchEntitySearchHydrator::class)]
class ElasticsearchEntitySearchHydratorTest extends TestCase
{
    private ElasticsearchEntitySearchHydrator $hydrator;

    private Context $context;

    protected function setUp(): void
    {
        $this->hydrator = new ElasticsearchEntitySearchHydrator();
        $this->context = Context::createDefaultContext();
    }

    public function testHydrateWithEmptyResult(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $result = [
            'hits' => [
                'hits' => [],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(0, $idSearchResult->getTotal());
        static::assertEmpty($idSearchResult->getIds());
    }

    public function testHydrateWithHits(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $result = [
            'hits' => [
                'hits' => [
                    [
                        '_id' => '1',
                        '_score' => 1.0,
                        '_source' => ['field' => 'value'],
                    ],
                    [
                        '_id' => '2',
                        '_score' => 2.0,
                        '_source' => ['field' => 'value'],
                    ],
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(2, $idSearchResult->getTotal());
        static::assertSame(['1', '2'], $idSearchResult->getIds());
    }

    public function testHydratePassesMatchedQueriesThroughInExplainMode(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $matchedQueries = ['{"field":"name","term":"iron","type":"exact"}' => 12.5];
        $result = [
            'hits' => [
                'hits' => [
                    [
                        '_id' => '1',
                        '_score' => 1.0,
                        '_source' => ['field' => 'value'],
                        'matched_queries' => $matchedQueries,
                    ],
                    [
                        '_id' => '2',
                        '_score' => 2.0,
                        '_source' => ['field' => 'value'],
                    ],
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $context, $result);

        // the per-clause explain scores survive into the hit data, absent hits stay untouched
        static::assertSame($matchedQueries, $idSearchResult->getDataFieldOfId('1', 'matched_queries'));
        static::assertNull($idSearchResult->getDataFieldOfId('2', 'matched_queries'));
    }

    public function testHydrateDropsMatchedQueriesOutsideExplainMode(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $result = [
            'hits' => [
                'hits' => [
                    [
                        '_id' => '1',
                        '_score' => 1.0,
                        '_source' => ['field' => 'value'],
                        'matched_queries' => ['{"field":"name","term":"iron"}' => 12.5],
                    ],
                ],
            ],
        ];

        // without explain mode the clause list must not leak into the hit data
        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertNull($idSearchResult->getDataFieldOfId('1', 'matched_queries'));
    }

    public function testHydrateWithoutTotal(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $result = [
            'hits' => [
                'hits' => [
                    [
                        '_id' => '1',
                        '_score' => 1.0,
                        '_source' => ['field' => 'value'],
                    ],
                    [
                        '_id' => '2',
                        '_score' => 2.0,
                        '_source' => ['field' => 'value'],
                    ],
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(2, $idSearchResult->getTotal());
    }

    public function testHydrateWithExactTotal(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = [
            'hits' => [
                'total' => [
                    'value' => 2,
                ],
                'hits' => [],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(2, $idSearchResult->getTotal());

        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $result = [
            'hits' => [
                'hits' => [],
            ],
            'aggregations' => [
                'total-count' => [
                    'value' => 3,
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(3, $idSearchResult->getTotal());

        $criteria->addPostFilter(new EqualsFilter('field', 'value'));
        $result = [
            'hits' => [
                'hits' => [],
            ],
            'aggregations' => [
                'total-filtered-count' => [
                    'total-count' => [
                        'value' => 3,
                    ],
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(3, $idSearchResult->getTotal());
    }

    public function testHydrateWithNestedHits(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = [
            'hits' => [
                'total' => [
                    'value' => 2,
                ],
                'hits' => [
                    [
                        '_id' => '1',
                        '_score' => 1.0,
                        '_source' => ['field' => 'value'],
                        'inner_hits' => [
                            'inner' => [
                                'hits' => [
                                    'hits' => [
                                        [
                                            '_id' => '2',
                                            '_score' => 2.0,
                                            '_source' => ['field' => 'value'],
                                        ],
                                        [
                                            '_id' => '3',
                                            '_score' => 2.0,
                                            '_source' => ['field' => 'value'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(2, $idSearchResult->getTotal());
        static::assertSame(['2', '3'], $idSearchResult->getIds());
    }

    public function testHydrateWithIdSorting(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $criteria = new Criteria(['2', '1']);
        $result = [
            'hits' => [
                'hits' => [
                    [
                        '_id' => '1',
                        '_score' => 1.0,
                        '_source' => ['field' => 'value'],
                    ],
                    [
                        '_id' => '2',
                        '_score' => 2.0,
                        '_source' => ['field' => 'value'],
                    ],
                ],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertSame(2, $idSearchResult->getTotal());
        static::assertSame(['2', '1'], $idSearchResult->getIds());
    }
}
