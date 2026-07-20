<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
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

    private LoggerInterface&MockObject $logger;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->scheduledTaskRepository = new StaticEntityRepository([]);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->ids = new IdsCollection();
    }

    public function testRunCleanupCorruptedMediaSuccessfully(): void
    {
        $data = [
            $this->ids->get('media-1') => ['primaryKey' => $this->ids->get('media-1'), 'data' => []],
            $this->ids->get('media-2') => ['primaryKey' => $this->ids->get('media-2'), 'data' => []],
        ];

        $this->mediaRepository = new StaticEntityRepository([
            function (Criteria $criteria, Context $context) use ($data): IdSearchResult {
                $this->assertCleanupFilters($criteria);
                static::assertSame(500, $criteria->getLimit());

                return new IdSearchResult(2, $data, $criteria, $context);
            },
            function (Criteria $criteria, Context $context): IdSearchResult {
                $this->assertCleanupFilters($criteria, $this->ids->get('media-2'));
                static::assertSame(500, $criteria->getLimit());

                return new IdSearchResult(0, [], $criteria, $context);
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
            function (Criteria $criteria, Context $context): IdSearchResult {
                $this->assertCleanupFilters($criteria);
                static::assertSame(500, $criteria->getLimit());

                return new IdSearchResult(0, [], $criteria, $context);
            },
        ]);

        $handler = $this->createHandler();
        $handler->run();

        static::assertEmpty($this->mediaRepository->deletes);
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
