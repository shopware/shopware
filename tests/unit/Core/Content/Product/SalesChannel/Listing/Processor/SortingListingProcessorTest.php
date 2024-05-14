<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SortingListingProcessor::class)]
class SortingListingProcessorTest extends TestCase
{
    private string $barId;

    private string $fooId;

    private string $testId;

    /**
     * @param FieldSorting[] $expected
     */
    #[DataProvider('prepareProvider')]
    public function testPrepare(string $sorting, bool $testWithAvailableSortings, array $expected): void
    {
        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            new StaticEntityRepository([$this->buildSortings()])
        );

        $processor->prepare(
            new Request(['order' => $sorting, 'availableSortings' => $testWithAvailableSortings ? $this->buildAvailableSortings() : []]),
            $criteria = new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals($expected, $criteria->getSorting());
    }

    #[DataProvider('processProvider')]
    public function testProcess(string $requested, ?string $expected): void
    {
        $sortings = $this->buildSortings();

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            new StaticEntityRepository([
                $sortings,
            ])
        );

        $result = new ProductListingResult($requested, 1, new ProductCollection(), null, new Criteria(), Context::createDefaultContext());
        $result->getCriteria()->addExtension('sortings', $sortings);

        $processor->process(
            new Request(['order' => $requested]),
            $result,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals($expected, $result->getSorting());
    }

    #[DataProvider('wrongSortingTypeProvider')]
    public function testWrongSortingTypeThrowsException(mixed $requested): void
    {
        $this->expectException(ProductException::class);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            new StaticEntityRepository([
                $this->buildSortings(),
            ])
        );

        $processor->prepare(
            new Request(['order' => $requested]),
            new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public static function prepareProvider(): \Generator
    {
        yield 'Requested foo sorting will be accepted' => [
            'sorting' => 'foo',
            'testWithAvailableSortings' => false,
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('foo', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested foo sorting with available sortings will be accepted' => [
            'sorting' => 'foo',
            'testWithAvailableSortings' => true,
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('foo', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested bar sorting will be accepted' => [
            'sorting' => 'bar',
            'testWithAvailableSortings' => false,
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('bar', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested bar sorting with available sortings will be accepted' => [
            'sorting' => 'bar',
            'testWithAvailableSortings' => true,
            'expected' => [],
        ];

        yield 'Requested unknown sorting will be accepted' => [
            'sorting' => 'test',
            'testWithAvailableSortings' => false,
            'expected' => [],
        ];

        yield 'Requested unknown with available sortings sorting will be accepted' => [
            'sorting' => 'test',
            'testWithAvailableSortings' => true,
            'expected' => [],
        ];
    }

    public static function processProvider(): \Generator
    {
        yield 'Requested foo sorting will be accepted' => [
            'requested' => 'foo',
            'expected' => 'foo',
        ];

        yield 'Requested bar sorting will be accepted' => [
            'requested' => 'bar',
            'expected' => 'bar',
        ];

        yield 'Requested unknown test sorting will be accepted' => [
            'requested' => 'test',
            'expected' => null,
        ];
    }

    public static function wrongSortingTypeProvider(): \Generator
    {
        yield 'Request of type null will throw exception' => ['requested' => null];
        yield 'Request of type array will throw exception' => ['requested' => []];
        yield 'Request of type int will throw exception' => ['requested' => 1];
    }

    private function buildSortings(): ProductSortingCollection
    {
        $this->fooId = Uuid::randomHex();
        $this->barId = Uuid::randomHex();
        $this->testId = Uuid::randomHex();

        $sortings = [
            (new ProductSortingEntity())->assign([
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new ProductSortingEntity())->assign([
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ];

        $sortings[0]->setId($this->fooId);
        $sortings[1]->setId($this->barId);

        return new ProductSortingCollection($sortings);
    }

    /**
     * @return ProductSortingEntity[]
     */
    private function buildAvailableSortings(): array
    {
        $availableSortings = [
            $this->fooId => (new ProductSortingEntity())->assign([
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            $this->testId => (new ProductSortingEntity())->assign([
                'key' => 'test',
                'fields' => [
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                    ['field' => 'test', 'priority' => 3, 'order' => 'DESC'],
                ],
            ]),
        ];

        $availableSortings[$this->fooId]->setId($this->fooId);

        return $availableSortings;
    }
}
