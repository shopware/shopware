<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DeleteExpiredFilesServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<ImportExportFileEntity>>
     */
    private EntityRepository $fileRepository;

    private DeleteExpiredFilesService $deleteExpiredFilesService;

    private Context $context;

    protected function setUp(): void
    {
        $this->fileRepository = static::getContainer()->get('import_export_file.repository');
        $this->deleteExpiredFilesService = static::getContainer()->get(DeleteExpiredFilesService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCountFilesWithNoExpiredFiles(): void
    {
        // Create some non-expired files
        $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('+1 day')],
            ['expireDate' => new \DateTimeImmutable('+10 days')],
            ['expireDate' => new \DateTimeImmutable('+30 days')],
        ]);

        $count = $this->deleteExpiredFilesService->countFiles($this->context);

        static::assertSame(0, $count);
    }

    public function testCountFilesWithExpiredFiles(): void
    {
        // Create expired files (older than 30 days)
        $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('-31 days')],
            ['expireDate' => new \DateTimeImmutable('-45 days')],
            ['expireDate' => new \DateTimeImmutable('-60 days')],
        ]);

        // Create non-expired files
        $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('+1 day')],
            ['expireDate' => new \DateTimeImmutable('-29 days')], // Not expired yet (less than 30 days)
        ]);

        $count = $this->deleteExpiredFilesService->countFiles($this->context);

        static::assertSame(3, $count);
    }

    public function testCountFilesWithNearlyThirtyDaysOldFiles(): void
    {
        // Create files nearly 30 days old
        $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('-29 days -23 hours -59 minutes -55 seconds')], // Should not expire (buffer of 5 seconds for test execution time)
            ['expireDate' => new \DateTimeImmutable('-30 days -1 second')], // Should expire
        ]);

        $count = $this->deleteExpiredFilesService->countFiles($this->context);

        static::assertSame(1, $count);
    }

    public function testDeleteFilesWithNoExpiredFiles(): void
    {
        $initialIds = $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('+1 day')],
            ['expireDate' => new \DateTimeImmutable('+10 days')],
        ]);

        $this->deleteExpiredFilesService->deleteFiles($this->context);

        // Verify all files still exist
        $remainingCount = $this->fileRepository->search(new Criteria(), $this->context)->getTotal();
        static::assertSame(\count($initialIds), $remainingCount);
    }

    public function testDeleteFilesWithExpiredFiles(): void
    {
        $expiredIds = $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('-31 days')],
            ['expireDate' => new \DateTimeImmutable('-45 days')],
        ]);

        $nonExpiredIds = $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('+1 day')],
            ['expireDate' => new \DateTimeImmutable('-29 days')],
        ]);

        $this->deleteExpiredFilesService->deleteFiles($this->context);

        // Verify expired files are deleted
        foreach ($expiredIds as $id) {
            $file = $this->fileRepository->search(new Criteria([$id]), $this->context)->first();
            static::assertNull($file, "Expired file with ID {$id} should be deleted");
        }

        // Verify non-expired files still exist
        foreach ($nonExpiredIds as $id) {
            $file = $this->fileRepository->search(new Criteria([$id]), $this->context)->first();
            static::assertNotNull($file, "Non-expired file with ID {$id} should still exist");
        }
    }

    public function testDeleteFilesWithMixedExpireDates(): void
    {
        $beforeExpiredCount = $this->deleteExpiredFilesService->countFiles($this->context);

        // Create a mix of expired and non-expired files
        $expiredIds = $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('-31 days')],
            ['expireDate' => new \DateTimeImmutable('-90 days')],
            ['expireDate' => new \DateTimeImmutable('-365 days')],
        ]);

        $nonExpiredIds = $this->createTestFiles([
            ['expireDate' => new \DateTimeImmutable('+1 day')],
            ['expireDate' => new \DateTimeImmutable('-1 day')],
            ['expireDate' => new \DateTimeImmutable('-29 days')],
        ]);

        // Verify count before deletion
        $expiredCount = $this->deleteExpiredFilesService->countFiles($this->context);
        static::assertSame($beforeExpiredCount + \count($expiredIds), $expiredCount);

        // Delete expired files
        $this->deleteExpiredFilesService->deleteFiles($this->context);

        // Verify count after deletion
        $remainingExpiredCount = $this->deleteExpiredFilesService->countFiles($this->context);
        static::assertSame($beforeExpiredCount, $remainingExpiredCount);

        // Verify the correct number of files remain in total
        $totalRemainingCount = $this->fileRepository->search(new Criteria(), $this->context)->getTotal();
        static::assertGreaterThanOrEqual(\count($nonExpiredIds), $totalRemainingCount);
    }

    public function testDeleteFilesWithEmptyDatabase(): void
    {
        // Ensure no files exist
        $allFiles = $this->fileRepository->searchIds(new Criteria(), $this->context)->getIds();
        if (!empty($allFiles)) {
            $deleteData = array_map(fn ($id) => ['id' => $id], $allFiles);
            $this->fileRepository->delete($deleteData, $this->context);
        }

        $count = $this->deleteExpiredFilesService->countFiles($this->context);
        static::assertSame(0, $count);

        // Should not throw exception when deleting from empty database
        $this->deleteExpiredFilesService->deleteFiles($this->context);

        $countAfter = $this->deleteExpiredFilesService->countFiles($this->context);
        static::assertSame(0, $countAfter);
    }

    /**
     * @param array<array<string, \DateTimeInterface>> $fileData
     *
     * @return array<string> Array of created file IDs
     */
    private function createTestFiles(array $fileData): array
    {
        $data = [];
        $ids = [];

        foreach ($fileData as $index => $fileInfo) {
            $id = Uuid::randomHex();
            $ids[] = $id;

            $data[] = [
                'id' => $id,
                'originalName' => \sprintf('test_file_%d.csv', $index),
                'path' => \sprintf('test/path/file_%d.csv', $index),
                'expireDate' => $fileInfo['expireDate'],
                'size' => 1024,
                'accessToken' => Random::getBase64UrlString(32),
            ];
        }

        $this->fileRepository->create($data, $this->context);

        return $ids;
    }
}
