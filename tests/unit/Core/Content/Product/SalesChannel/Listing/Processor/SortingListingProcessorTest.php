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
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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
        /** @var StaticEntityRepository<ProductSortingCollection> $sortingRepository */
        $sortingRepository = new StaticEntityRepository([$this->buildSortings()]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
        );

        $processor->prepare(
            new Request(['order' => $sorting, 'availableSortings' => $testWithAvailableSortings ? $this->buildAvailableSortings() : []]),
            $criteria = new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals($expected, $criteria->getSorting());
    }

    public function testPrepareDefaultSearchResultSorting(): void
    {
        $productSorting = new ProductSortingEntity();
        $productSorting->setId(Uuid::randomHex());
        $productSorting->assign([
            'key' => 'score',
            'fields' => [
                ['field' => '_score', 'priority' => 1, 'order' => 'DESC'],
            ],
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                ProductSortingDefinition::ENTITY_NAME,
                1,
                new ProductSortingCollection([$productSorting]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.defaultSearchResultSorting' => Uuid::randomHex(),
            ]),
            $repository
        );

        $processor->prepare(
            new Request(['search' => 'test']),
            $criteria = new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals([
            new FieldSorting('_score', FieldSorting::DESCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING),
        ], $criteria->getSorting());
    }

    public function testPrepareWithRestrictedSortings(): void
    {
        /** @var StaticEntityRepository<ProductSortingCollection> $sortingRepository */
        $sortingRepository = new StaticEntityRepository([
            $this->buildRestrictedProductSortingCollection(),
        ]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
        );

        $restrictedCollection = $this->buildRestrictedProductSortingCollection();
        $request = new Request(['order' => 'foo']);
        $request->attributes->set('restrictedProductSortingCollection', $restrictedCollection);

        $processor->prepare(
            $request,
            $criteria = new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        $expected = [
            new FieldSorting('id', FieldSorting::ASCENDING),
            new FieldSorting('foo', FieldSorting::DESCENDING),
        ];

        static::assertEquals($expected, $criteria->getSorting());

        // Verify sortings extension contains the restrictedProductSortingCollection
        $sortings = $criteria->getExtension('sortings');
        static::assertInstanceOf(ProductSortingCollection::class, $sortings);
        static::assertSame($restrictedCollection, $sortings);
    }

    #[DataProvider('processProvider')]
    public function testProcess(string $requested, ?string $expected): void
    {
        $sortings = $this->buildSortings();

        /** @var StaticEntityRepository<ProductSortingCollection> $sortingRepository */
        $sortingRepository = new StaticEntityRepository([$sortings]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
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

        /** @var StaticEntityRepository<ProductSortingCollection> $sortingRepository */
        $sortingRepository = new StaticEntityRepository([
            $this->buildSortings(),
        ]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
        );

        $processor->prepare(
            new Request(['order' => $requested]),
            new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testGetAvailableSortingsWithValidUuids(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createMock(EntityRepository::class);

        // Create test UUIDs
        $uuid1 = Uuid::randomHex();
        $uuid2 = Uuid::randomHex();

        // Create sortings with these IDs
        $sorting1 = new ProductSortingEntity();
        $sorting1->setId($uuid1);
        $sorting1->setUniqueIdentifier($uuid1);
        $sorting1->assign(['key' => 'sort1', 'active' => true]);

        $sorting2 = new ProductSortingEntity();
        $sorting2->setId($uuid2);
        $sorting2->setUniqueIdentifier($uuid2);
        $sorting2->assign(['key' => 'sort2', 'active' => true]);

        // Set up the repository mock to return these sortings when searched
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                ProductSortingDefinition::ENTITY_NAME,
                2,
                new ProductSortingCollection([$sorting1, $sorting2]),
                null,
                new Criteria(),
                $context
            )
        );

        // Create request with availableSortings containing valid UUIDs
        $request = new Request([
            'availableSortings' => [
                $uuid1 => 10,
                $uuid2 => 20,
                'not-a-uuid' => 5, // This should be filtered out by line 96
            ],
        ]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $repository
        );

        $processor->prepare(
            $request,
            $criteria = new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        // Check that sortings extension was properly added
        $sortings = $criteria->getExtension('sortings');
        static::assertInstanceOf(ProductSortingCollection::class, $sortings);
        static::assertCount(2, $sortings);

        // Verify that sortings are ordered based on the requested priority
        // The second sorting (uuid2) has higher priority (20) so it should come first
        $firstSorting = $sortings->first();
        $lastSorting = $sortings->last();
        static::assertNotNull($firstSorting);
        static::assertNotNull($lastSorting);
        static::assertEquals($uuid2, $firstSorting->getId());
        static::assertEquals($uuid1, $lastSorting->getId());
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
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('bar', FieldSorting::DESCENDING),
            ],
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
        $sortings[0]->setUniqueIdentifier($this->fooId);
        $sortings[1]->setId($this->barId);
        $sortings[1]->setUniqueIdentifier($this->barId);

        return new ProductSortingCollection($sortings);
    }

    private function buildRestrictedProductSortingCollection(): ProductSortingCollection
    {
        $collection = new ProductSortingCollection();
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

        $fooId = Uuid::randomHex();
        $barId = Uuid::randomHex();

        $sortings[0]->setId($fooId);
        $sortings[0]->setUniqueIdentifier($fooId);
        $sortings[1]->setId($barId);
        $sortings[1]->setUniqueIdentifier($barId);

        $collection->add($sortings[0]);
        $collection->add($sortings[1]);

        return $collection;
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
            $this->barId => (new ProductSortingEntity())->assign([
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
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
        $availableSortings[$this->barId]->setId($this->barId);
        $availableSortings[$this->testId]->setId($this->testId);

        return $availableSortings;
    }
}
