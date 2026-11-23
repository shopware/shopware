<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileCollection;
use Shopware\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DeleteExpiredFilesService::class)]
class DeleteExpiredFilesServiceTest extends TestCase
{
    public function testCountFilesUsesExpiredDateCutoffAndExactCount(): void
    {
        $now = new \DateTimeImmutable('2026-01-31T12:00:00+00:00');

        $repository = StaticEntityRepository::of(ImportExportFileCollection::class, [
            static function (Criteria $criteria, Context $context) use ($now): IdSearchResult {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(RangeFilter::class, $filters[0]);
                static::assertSame('expireDate', $filters[0]->getField());
                static::assertSame(
                    $now->modify('-30 days')->format(\DATE_ATOM),
                    $filters[0]->getParameter(RangeFilter::LT)
                );

                return IdSearchResult::fromIds([], $criteria, $context, 4);
            },
        ]);

        static::assertSame(4, (new DeleteExpiredFilesService($repository, new MockClock($now)))->countFiles(Context::createDefaultContext()));
    }

    public function testDeleteFilesDeletesExpiredFileIds(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $repository = StaticEntityRepository::of(ImportExportFileCollection::class, [$ids]);

        (new DeleteExpiredFilesService($repository, new MockClock('2026-01-31 12:00:00')))->deleteFiles(Context::createDefaultContext());

        static::assertSame([['id' => $ids[0]], ['id' => $ids[1]]], $repository->deletes[0]);
    }

    public function testDeleteFilesDeletesNothingWhenNoExpiredFilesFound(): void
    {
        $repository = StaticEntityRepository::of(ImportExportFileCollection::class, [[]]);

        (new DeleteExpiredFilesService($repository, new MockClock('2026-01-31 12:00:00')))->deleteFiles(Context::createDefaultContext());

        static::assertSame([[]], $repository->deletes);
    }
}
