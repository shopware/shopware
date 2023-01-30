<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater
 */
class ProductStreamUpdaterTest extends TestCase
{
    private Connection&MockObject $connection;

    private MockObject&ProductDefinition $productDefinition;

    private MockObject&EntityRepository $repository;

    private ProductStreamUpdater $updater;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->productDefinition = $this->createMock(ProductDefinition::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $manyToManyIdFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $this->updater = new ProductStreamUpdater(
            $this->connection,
            $this->productDefinition,
            $this->repository,
            $messageBus,
            $manyToManyIdFieldUpdater
        );
    }

    /**
     * @dataProvider filterProvider
     *
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    public function testCriteria(array $ids, array $filters, Criteria $criteria): void
    {
        $context = Context::createDefaultContext();

        $this->productDefinition
            ->method('getEntityName')
            ->willReturn('product');

        $this->connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn($filters);

        $this->repository
            ->expects(static::once())
            ->method('searchIds')
            ->with($criteria, $context);

        $this->updater->updateProducts($ids, $context);
    }

    /**
     * @return iterable<string, array<int, array<int, array<string, bool|string>|string>|Criteria>>
     */
    public function filterProvider(): iterable
    {
        yield 'Active filter' => [
            ['foobar'],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new EqualsFilter('product.active', true),
                new EqualsAnyFilter('id', ['foobar'])
            ),
        ];

        yield 'Price filter' => [
            ['foobar'],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'range',
                        'field' => 'product.cheapestPrice',
                        'parameters' => [
                            'lte' => 50,
                        ],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                    new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
                ]),
                new EqualsAnyFilter('id', ['foobar'])
            ),
        ];

        yield 'Nested price filter' => [
            ['foobar'],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'product.cheapestPrice',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_OR, [
                        new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                        new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
                    ]),
                ]),
                new EqualsAnyFilter('id', ['foobar'])
            ),
        ];

        yield 'Nested price percentage filter' => [
            ['foobar'],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'cheapestPrice.percentage',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_OR, [
                        new RangeFilter('product.price.percentage', [RangeFilter::LTE => 50]),
                        new RangeFilter('product.prices.price.percentage', [RangeFilter::LTE => 50]),
                    ]),
                ]),
                new EqualsAnyFilter('id', ['foobar'])
            ),
        ];
    }
}
