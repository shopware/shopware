<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(RequestCriteriaBuilder::class)]
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

    #[DataProvider('maxApiLimitProvider')]
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
     * @param array<mixed> $ids
     */
    #[DataProvider('invalidCriteriaIdsProvider')]
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
     * @param string|array<mixed> $idPayload
     * @param array<string>|array<int, array<string>> $expectedIds
     */
    #[DataProvider('validCriteriaIdsProvider')]
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

    /**
     * @param array<string, mixed> $sortingPayload
     * @param list<FieldSorting> $expectedParsedSortings
     */
    #[DataProvider('sortingCaseProvider')]
    public function testSorting(array $sortingPayload, array $expectedParsedSortings): void
    {
        $request = new Request([], $sortingPayload, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(ProductDefinition::class),
            Context::createDefaultContext()
        );

        $sorting = $criteria->getSorting();
        static::assertCount(\count($expectedParsedSortings), $sorting);
        foreach ($expectedParsedSortings as $index => $expectedParsedSorting) {
            static::assertInstanceOf($expectedParsedSorting::class, $sorting[$index]);
            static::assertEquals($expectedParsedSorting->getField(), $sorting[$index]->getField());
            static::assertEquals($expectedParsedSorting->getDirection(), $sorting[$index]->getDirection());
            static::assertEquals($expectedParsedSorting->getNaturalSorting(), $sorting[$index]->getNaturalSorting());
        }
    }

    public static function sortingCaseProvider(): \Generator
    {
        yield 'manual score sorting' => [
            [
                'sort' => [
                    [
                        'field' => '_score',
                    ],
                ],
            ],
            [
                new FieldSorting('_score'),
            ],
        ];

        yield 'multiple sortings' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'order' => 'ASC',
                        'naturalSorting' => true,
                    ],
                    [
                        'field' => 'price',
                        'order' => 'DESC',
                    ],
                ],
            ],
            [
                new FieldSorting('product.id', FieldSorting::ASCENDING, true),
                new FieldSorting('product.price', FieldSorting::DESCENDING),
            ],
        ];

        yield 'count sorting' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'type' => 'count',
                    ],
                ],
            ],
            [
                new CountSorting('product.id'),
            ],
        ];

        yield 'simple sorting' => [
            [
                'sort' => 'id',
            ],
            [
                new FieldSorting('product.id'),
            ],
        ];

        yield 'multiple simple sortings' => [
            [
                'sort' => 'id,-price',
            ],
            [
                new FieldSorting('product.id'),
                new FieldSorting('product.price', FieldSorting::DESCENDING),
            ],
        ];

        yield 'empty array sorting' => [
            [
                'sort' => [],
            ],
            [],
        ];

        yield 'invalid order option falls back to ascending' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'order' => 'invalid',
                    ],
                ],
            ],
            [
                new FieldSorting('product.id'),
            ],
        ];

        yield 'true-ish naturals sort option' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'naturalSorting' => '1',
                    ],
                ],
            ],
            [
                new FieldSorting('product.id', FieldSorting::ASCENDING, true),
            ],
        ];

        yield 'false-ish naturals sort option' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'naturalSorting' => '0',
                    ],
                ],
            ],
            [
                new FieldSorting('product.id'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $sortingPayload
     */
    #[DataProvider('invalidSortingCaseProvider')]
    public function testInvalidSorting(array $sortingPayload, InvalidSortQueryException $expected): void
    {
        $request = new Request([], $sortingPayload, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $wasThrown = false;

        try {
            $this->requestCriteriaBuilder->handleRequest(
                $request,
                new Criteria(),
                $this->staticDefinitionRegistry->get(ProductDefinition::class),
                Context::createDefaultContext()
            );
        } catch (SearchRequestException $e) {
            $sortException = $e->getErrors()->current();
            static::assertEquals($expected->getErrorCode(), $sortException['code']);
            static::assertEquals($expected->getMessage(), $sortException['detail']);
            static::assertEquals($expected->getStatusCode(), $sortException['status']);
            static::assertEquals($expected->getParameter('path'), $sortException['source']['pointer']);

            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
    }

    public static function invalidSortingCaseProvider(): \Generator
    {
        yield 'empty string sorting' => [
            [
                'sort' => '',
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" parameter needs to be a sorting array or a comma separated list of fields', '/sort'),
        ];

        yield 'empty array sorting' => [
            [
                'sort' => [[]],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/0'),
        ];

        yield 'non nested array' => [
            [
                'sort' => ['id'],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/0'),
        ];

        yield 'field is not a string' => [
            [
                'sort' => [['field' => 1]],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/0'),
        ];

        yield 'array invalid second sorting' => [
            [
                'sort' => [['field' => 'id'], []],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/1'),
        ];
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

    public function testInvalidAssociationsCriteria(): void
    {
        $payload = [
            'associations' => [
                'prices' => 'invalid',
            ],
        ];

        $criteria = $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());

        static::assertEmpty($criteria->getAssociations());
    }

    #[DataProvider('providerTotalCount')]
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

    /**
     * @param array<string, mixed> $pagingPayload
     */
    #[DataProvider('providerPaging')]
    public function testPaging(array $pagingPayload, ?int $expectedOffset, ?int $expectedLimit): void
    {
        $criteria = $this->requestCriteriaBuilder->fromArray($pagingPayload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        static::assertSame($expectedOffset, $criteria->getOffset());
        static::assertSame($expectedLimit, $criteria->getLimit());
    }

    public static function providerPaging(): \Generator
    {
        yield 'offset correctly calculated' => [
            ['page' => 3, 'limit' => 10],
            20,
            10,
        ];

        yield 'no page' => [
            ['limit' => 10],
            null,
            10,
        ];

        yield 'no limit' => [
            ['page' => '3'],
            0,
            null,
        ];

        yield 'no paging info' => [
            [],
            null,
            null,
        ];
    }

    /**
     * @param array<string, mixed> $pagingPayload
     */
    #[DataProvider('providerInvalidPaging')]
    public function testInvalidPaging(array $pagingPayload, string $expectedExceptionCode, string $path): void
    {
        $wasThrown = false;

        try {
            $this->requestCriteriaBuilder->fromArray($pagingPayload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            $sortException = $e->getErrors()->current();
            static::assertEquals($expectedExceptionCode, $sortException['code']);
            static::assertEquals(400, $sortException['status']);
            static::assertEquals($path, $sortException['source']['pointer']);

            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
    }

    public static function providerInvalidPaging(): \Generator
    {
        yield 'empty page' => [
            ['page' => '', 'limit' => 10],
            'FRAMEWORK__INVALID_PAGE_QUERY',
            '/page',
        ];

        yield 'negative page' => [
            ['page' => '-3', 'limit' => 10],
            'FRAMEWORK__INVALID_PAGE_QUERY',
            '/page',
        ];

        yield 'page is string' => [
            ['page' => 'foo', 'limit' => 10],
            'FRAMEWORK__INVALID_PAGE_QUERY',
            '/page',
        ];

        yield 'negative limit' => [
            ['page' => '3', 'limit' => '-10'],
            'FRAMEWORK__INVALID_LIMIT_QUERY',
            '/limit',
        ];

        yield 'empty limit' => [
            ['page' => '3', 'limit' => ''],
            'FRAMEWORK__INVALID_LIMIT_QUERY',
            '/limit',
        ];

        yield 'limit is string' => [
            ['page' => '3', 'limit' => 'foo'],
            'FRAMEWORK__INVALID_LIMIT_QUERY',
            '/limit',
        ];
    }

    public function testSimpleFilterAddsExceptionWithArrayInValue(): void
    {
        $payload = [
            'filter' => [
                'name' => ['test'],
            ],
        ];

        $this->expectException(SearchRequestException::class);

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            $error = $e->getErrors()->current();

            static::assertEquals('FRAMEWORK__INVALID_FILTER_QUERY', $error['code']);
            static::assertEquals('The value for filter "name" must be scalar.', $error['detail']);
            static::assertEquals(400, $error['status']);

            throw $e;
        }
    }

    public function testFilterElementIsInvalid(): void
    {
        $payload = [
            'filter' => [
                0 => 'test',
            ],
        ];

        $this->expectException(SearchRequestException::class);

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(ProductDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            $error = $e->getErrors()->current();

            static::assertEquals('FRAMEWORK__INVALID_FILTER_QUERY', $error['code']);
            static::assertEquals('The filter parameter has to be an array.', $error['detail']);
            static::assertEquals(400, $error['status']);

            throw $e;
        }
    }
}
