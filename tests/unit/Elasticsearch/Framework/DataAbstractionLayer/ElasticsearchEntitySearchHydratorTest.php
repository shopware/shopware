<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearchHydrator;

/**
 * @internal
 */
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
        $definition = $this->createMock(ProductDefinition::class);
        $criteria = new Criteria();
        $result = [
            'hits' => [
                'hits' => [],
            ],
        ];

        $idSearchResult = $this->hydrator->hydrate($definition, $criteria, $this->context, $result);

        static::assertEquals(0, $idSearchResult->getTotal());
        static::assertEmpty($idSearchResult->getIds());
    }

    public function testHydrateWithHits(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
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

        static::assertEquals(2, $idSearchResult->getTotal());
        static::assertEquals(['1', '2'], $idSearchResult->getIds());
    }

    public function testHydrateWithoutTotal(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
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

        static::assertEquals(2, $idSearchResult->getTotal());
    }

    public function testHydrateWithExactTotal(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
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

        static::assertEquals(2, $idSearchResult->getTotal());

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

        static::assertEquals(3, $idSearchResult->getTotal());

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

        static::assertEquals(3, $idSearchResult->getTotal());
    }

    public function testHydrateWithNestedHits(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
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

        static::assertEquals(2, $idSearchResult->getTotal());
        static::assertEquals(['2', '3'], $idSearchResult->getIds());
    }

    public function testHydrateWithIdSorting(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
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

        static::assertEquals(2, $idSearchResult->getTotal());
        static::assertEquals(['2', '1'], $idSearchResult->getIds());
    }
}
