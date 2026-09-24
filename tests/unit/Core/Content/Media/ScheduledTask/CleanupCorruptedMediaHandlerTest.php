<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\FilelessMediaTypeInterface;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CleanupCorruptedMediaHandler::class)]
class CleanupCorruptedMediaHandlerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<ScheduledTaskCollection>
     */
    private StaticEntityRepository $scheduledTaskRepository;

    private LoggerInterface&Stub $logger;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->scheduledTaskRepository = new StaticEntityRepository([]);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->ids = new IdsCollection();
    }

    public function testRunCleanupCorruptedMediaSuccessfully(): void
    {
        $this->mediaRepository = new StaticEntityRepository([
            function (Criteria $criteria): MediaCollection {
                $this->assertCleanupFilters($criteria);
                static::assertSame(500, $criteria->getLimit());

                return new MediaCollection([
                    $this->media('media-1', new ImageType()),
                    $this->media('media-2', new ImageType()),
                ]);
            },
            function (Criteria $criteria): MediaCollection {
                $this->assertCleanupFilters($criteria, $this->ids->get('media-2'));
                static::assertSame(500, $criteria->getLimit());

                return new MediaCollection();
            },
        ]);

        $handler = $this->createHandler();
        $handler->run();

        $deletes = $this->mediaRepository->deletes[0];
        static::assertIsArray($deletes);

        $deletedIds = array_column($deletes, 'id');
        static::assertCount(2, $deletedIds);

        static::assertSame($this->ids->get('media-1'), $deletedIds[0]);
        static::assertSame($this->ids->get('media-2'), $deletedIds[1]);
    }

    public function testRunCleansNothingUpIfNoCorruptedMediaExists(): void
    {
        $this->mediaRepository = new StaticEntityRepository([
            function (Criteria $criteria): MediaCollection {
                $this->assertCleanupFilters($criteria);
                static::assertSame(500, $criteria->getLimit());

                return new MediaCollection();
            },
        ]);

        $handler = $this->createHandler();
        $handler->run();

        static::assertEmpty($this->mediaRepository->deletes);
    }

    public function testMediaThatIsMeantToHaveNoFileIsKept(): void
    {
        $this->mediaRepository = new StaticEntityRepository([
            fn (): MediaCollection => new MediaCollection([
                $this->media('carrier', $this->filelessType()),
                $this->media('broken', new ImageType()),
            ]),
            fn (): MediaCollection => new MediaCollection(),
        ]);

        $handler = $this->createHandler();
        $handler->run();

        $deletes = $this->mediaRepository->deletes[0];
        static::assertIsArray($deletes);
        static::assertSame([['id' => $this->ids->get('broken')]], $deletes);
    }

    public function testPagingGoesOnWhenAWholeBatchIsKept(): void
    {
        $searches = 0;

        $this->mediaRepository = new StaticEntityRepository([
            function (Criteria $criteria) use (&$searches): MediaCollection {
                ++$searches;
                $this->assertCleanupFilters($criteria);

                return new MediaCollection([$this->media('carrier', $this->filelessType())]);
            },
            function (Criteria $criteria) use (&$searches): MediaCollection {
                ++$searches;
                // Without an id to page past, the same batch would come back for ever.
                $this->assertCleanupFilters($criteria, $this->ids->get('carrier'));

                return new MediaCollection();
            },
        ]);

        $handler = $this->createHandler();
        $handler->run();

        static::assertSame(2, $searches);
        static::assertEmpty($this->mediaRepository->deletes);
    }

    private function media(string $key, MediaType $type): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($this->ids->get($key));
        $media->setUniqueIdentifier($this->ids->get($key));
        $media->setMediaType($type);

        return $media;
    }

    private function filelessType(): MediaType
    {
        return new class extends MediaType implements FilelessMediaTypeInterface {
            protected string $name = 'FILE_OPTIONAL';
        };
    }

    private function createHandler(): CleanupCorruptedMediaHandler
    {
        return new CleanupCorruptedMediaHandler($this->scheduledTaskRepository, $this->logger, $this->mediaRepository, new NativeClock());
    }

    private function assertCleanupFilters(Criteria $criteria, ?string $lastId = null): void
    {
        $sorting = $criteria->getSorting();
        static::assertCount(1, $sorting);
        static::assertSame('id', $sorting[0]->getField());
        static::assertSame(FieldSorting::ASCENDING, $sorting[0]->getDirection());

        $equalsFilters = array_values(array_filter(
            $criteria->getFilters(),
            static fn ($filter): bool => $filter instanceof EqualsFilter && $filter->getValue() === null
        ));

        $fields = array_map(static fn (EqualsFilter $filter): string => $filter->getField(), $equalsFilters);
        sort($fields);

        static::assertSame(
            ['path', 'uploadedAt'],
            $fields
        );

        $rangeFilters = array_values(array_filter(
            $criteria->getFilters(),
            static fn ($filter): bool => $filter instanceof RangeFilter
        ));

        if ($lastId === null) {
            static::assertCount(1, $rangeFilters);
            static::assertSame('createdAt', $rangeFilters[0]->getField());
            static::assertTrue($rangeFilters[0]->hasParameter(RangeFilter::LT));
            static::assertIsString($rangeFilters[0]->getParameter(RangeFilter::LT));

            return;
        }

        static::assertCount(2, $rangeFilters);

        $rangeFields = array_map(static fn (RangeFilter $filter): string => $filter->getField(), $rangeFilters);
        sort($rangeFields);
        static::assertSame(['createdAt', 'id'], $rangeFields);

        $idRangeFilters = array_values(array_filter(
            $rangeFilters,
            static fn (RangeFilter $filter): bool => $filter->getField() === 'id'
        ));

        static::assertArrayHasKey(0, $idRangeFilters);
        $idRangeFilter = $idRangeFilters[0];

        static::assertTrue($idRangeFilter->hasParameter(RangeFilter::GT));
        static::assertSame(Uuid::fromHexToBytes($lastId), $idRangeFilter->getParameter(RangeFilter::GT));
    }
}
