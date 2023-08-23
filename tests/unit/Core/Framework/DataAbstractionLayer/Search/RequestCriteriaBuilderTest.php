<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder
 */
class RequestCriteriaBuilderTest extends TestCase
{
    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private StaticDefinitionInstanceRegistry $staticDefinitionRegistry;

    protected function setUp(): void
    {
        $aggregationParser = new AggregationParser();

        $this->staticDefinitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                new ProductDefinition(),
                new ProductOptionDefinition(),
                new PropertyGroupOptionDefinition(),
                new ProductPriceDefinition(),
                new ProductCategoryDefinition(),
                new CategoryDefinition(),
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $this->requestCriteriaBuilder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
        );
    }

    /**
     * @return iterable<string, array{int|null, int|null, int|null, bool}>
     */
    public static function maxApiLimitProvider(): iterable
    {
        yield 'Test null max limit' => [10000, null, 10000, false];
        yield 'Test null max limit and null limit' => [null, null, null, false];
        yield 'Test max limit with null limit' => [null, 100, 100, false];
        yield 'Test max limit with higher limit' => [200, 100, 100, true];
        yield 'Test max limit with lower limit' => [50, 100, 50, false];
    }

    /**
     * @dataProvider maxApiLimitProvider
     */
    public function testMaxApiLimit(?int $limit, ?int $max, ?int $expected, bool $exception = false): void
    {
        $body = ['limit' => $limit];

        $aggregationParser = new AggregationParser();

        $builder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
            $max
        );

        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        try {
            $criteria = $builder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
            static::assertSame($expected, $criteria->getLimit());
        } catch (SearchRequestException) {
            static::assertTrue($exception);
        }

        $request = new Request($body);
        $request->setMethod(Request::METHOD_GET);

        try {
            $criteria = $builder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
            static::assertSame($expected, $criteria->getLimit());
        } catch (SearchRequestException) {
            static::assertTrue($exception);
        }
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function invalidCriteriaIdsProvider(): iterable
    {
        yield 'non string list' => [[123, 456]];
        yield 'non string key values' => [[[['foo'], ['bar']]]];
        yield 'non string values' => [[[['pk-1' => 123], ['pk-2' => 456]]]];
    }

    /**
     * @dataProvider invalidCriteriaIdsProvider
     *
     * @param array<mixed> $ids
     */
    public function testInvalidCriteriaIds(array $ids): void
    {
        $body = ['ids' => $ids];

        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        $postExceptionThrown = false;

        try {
            $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        } catch (DataAbstractionLayerException $e) {
            static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
            static::assertEquals('FRAMEWORK__INVALID_API_CRITERIA_IDS', $e->getErrorCode());
            $postExceptionThrown = true;
        }

        $request = new Request($body);
        $request->setMethod(Request::METHOD_GET);

        $getExceptionThrown = false;

        try {
            $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        } catch (DataAbstractionLayerException $e) {
            static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
            static::assertEquals('FRAMEWORK__INVALID_API_CRITERIA_IDS', $e->getErrorCode());
            $getExceptionThrown = true;
        }

        static::assertTrue($postExceptionThrown);
        static::assertTrue($getExceptionThrown);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function validCriteriaIdsProvider(): iterable
    {
        yield 'plain id list' => [['id1', 'id2'], ['id1', 'id2']];
        yield 'plain id' => ['id1', ['id1']];
        yield 'string concatenated id list' => ['id1|id2', ['id1', 'id2']];
        yield 'multiple pks' => [[['pk-1' => 'id1.1', 'pk-2' => 'id1.2'], ['pk-1' => 'id2.1', 'pk-2' => 'id2.2']], [['pk-1' => 'id1.1', 'pk-2' => 'id1.2'], ['pk-1' => 'id2.1', 'pk-2' => 'id2.2']]];
    }

    /**
     * @dataProvider validCriteriaIdsProvider
     *
     * @param string|array<mixed> $idPayload
     * @param array<string>|array<int, array<string>> $expectedIds
     */
    public function testValidCriteriaIds($idPayload, array $expectedIds): void
    {
        $body = ['ids' => $idPayload];

        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        static::assertEquals($expectedIds, $criteria->getIds());

        $request = new Request($body);
        $request->setMethod(Request::METHOD_GET);

        $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        static::assertEquals($expectedIds, $criteria->getIds());
    }

    public function testAssociationsAddedToCriteria(): void
    {
        $body = [
            'limit' => 10,
            'page' => 1,
            'associations' => [
                'prices' => [
                    'limit' => 25,
                    'page' => 1,
                    'filter' => [
                        ['type' => 'equals', 'field' => 'quantityStart', 'value' => 1],
                    ],
                    'sort' => [
                        ['field' => 'quantityStart'],
                    ],
                ],
            ],
        ];

        $request = new Request([], $body, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = new Criteria();
        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->staticDefinitionRegistry->get(ProductDefinition::class),
            Context::createDefaultContext()
        );

        static::assertTrue($criteria->hasAssociation('prices'));
        $nested = $criteria->getAssociation('prices');

        static::assertCount(1, $nested->getFilters());
        static::assertCount(1, $nested->getSorting());
    }

    public function testCriteriaToArray(): void
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING))
            ->addSorting(new CountSorting('transactions.id', CountSorting::ASCENDING))
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->setLimit(1)
            ->setOffset((1 - 1) * 1)
            ->setTotalCountMode(100);

        $criteria->getAssociation('transaction')->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $criteriaArray = $this->requestCriteriaBuilder->toArray($criteria);

        $testArray = [
            'total-count-mode' => 100,
            'limit' => 1,
            'associations' => [
                'transactions' => [
                    'total-count-mode' => 0,
                    'associations' => [
                        'paymentMethod' => [
                            'total-count-mode' => 0,
                        ],
                    ],
                ],
                'deliveries' => [
                    'total-count-mode' => 0,
                    'associations' => [
                        'shippingMethod' => [
                            'total-count-mode' => 0,
                        ],
                    ],
                ],
                'transaction' => [
                    'total-count-mode' => 0,
                    'sort' => [
                        [
                            'field' => 'createdAt',
                            'naturalSorting' => false,
                            'extensions' => [],
                            'order' => 'DESC',
                        ],
                    ],
                ],
            ],
            'sort' => [
                [
                    'field' => 'order.createdAt',
                    'naturalSorting' => false,
                    'extensions' => [],
                    'order' => 'DESC',
                ],
                [
                    'field' => 'transactions.id',
                    'naturalSorting' => false,
                    'extensions' => [],
                    'order' => 'ASC',
                    'type' => 'count',
                ],
            ],
        ];

        static::assertEquals($testArray, $criteriaArray);
    }

    public function testManualScoreSorting(): void
    {
        $body = [
            'sort' => [
                [
                    'field' => '_score',
                ],
            ],
        ];
        $request = new Request([], $body, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(ProductDefinition::class),
            Context::createDefaultContext()
        );

        $sorting = $criteria->getSorting();
        static::assertCount(1, $sorting);
        static::assertEquals('_score', $sorting[0]->getField());
    }

    public function testMaxLimitForAssociations(): void
    {
        $aggregationParser = new AggregationParser();
        $builder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
            100
        );

        $payload = [
            'associations' => [
                'options' => ['limit' => 101],
                'prices' => ['limit' => null],
                'categories' => [],
            ],
        ];

        $criteria = $builder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());

        static::assertTrue($criteria->hasAssociation('options'));
        static::assertTrue($criteria->hasAssociation('categories'));

        static::assertEquals(100, $criteria->getLimit());
        static::assertEquals(101, $criteria->getAssociation('options')->getLimit());
        static::assertNull($criteria->getAssociation('prices')->getLimit());
        static::assertNull($criteria->getAssociation('categories')->getLimit());
    }

    public function testInvalidAssociations(): void
    {
        $payload = [
            'associations' => [
                1 => [],
            ],
        ];

        static::expectException(AssociationNotFoundException::class);
        static::expectExceptionMessage('Can not find association by name 1');

        $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
    }

    /**
     * @dataProvider providerTotalCount
     */
    public function testDifferentTotalCount(mixed $totalCountMode, int $expectedMode): void
    {
        $payload = [
            'total-count-mode' => $totalCountMode,
        ];

        $criteria = $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        static::assertSame($expectedMode, $criteria->getTotalCountMode());
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function providerTotalCount(): iterable
    {
        yield [
            '0',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            '1',
            Criteria::TOTAL_COUNT_MODE_EXACT,
        ];

        yield [
            '2',
            Criteria::TOTAL_COUNT_MODE_NEXT_PAGES,
        ];

        yield [
            '3',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            '-3',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            'none',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            'none-2',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            'exact',
            Criteria::TOTAL_COUNT_MODE_EXACT,
        ];

        yield [
            'next-pages',
            Criteria::TOTAL_COUNT_MODE_NEXT_PAGES,
        ];
    }
}
