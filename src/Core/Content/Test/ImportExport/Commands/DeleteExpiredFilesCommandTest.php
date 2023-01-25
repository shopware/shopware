<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Commands;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Command\DeleteExpiredFilesCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('system-settings')]
class DeleteExpiredFilesCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $fileRepository;

    /**
     * @var DeleteExpiredFilesCommand
     */
    private $deleteExpiredFilesCommand;

    private Context $context;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->fileRepository = $this->getContainer()->get('import_export_file.repository');

        $this->deleteExpiredFilesCommand = $this->getContainer()->get(DeleteExpiredFilesCommand::class);

        $this->context = Context::createDefaultContext();

        $this->filesystem = $this->getFilesystem('shopware.filesystem.private');
    }

    public function testExecuteWithoutExpiredFiles(): void
    {
        $commandTester = new CommandTester($this->deleteExpiredFilesCommand);
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();
        static::assertMatchesRegularExpression('/\/\/ No expired files found./', $message);
    }

    public function testExecuteWithAllFilesExpired(): void
    {
        $num = 5;
        $data = $this->prepareImportExportFileTestData($num);

        $filePathes = [];
        foreach (array_keys($data) as $key) {
            $filePathes[] = $data[$key]['path'];
            $data[$key]['expireDate'] = date('Y-m-d H:i:s', strtotime('-1 second'));
        }

        $this->fileRepository->create(array_values($data), $this->context);

        $commandTester = new CommandTester($this->deleteExpiredFilesCommand);
        $commandTester->setInputs(['y']);
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();
        static::assertMatchesRegularExpression(
            sprintf('/Are you sure that you want to delete %d expired files\? \(yes\/no\) \[no\]:/', $num),
            $message
        );
        static::assertMatchesRegularExpression(sprintf('/\[OK\] Successfully deleted %d expired files./', $num), $message);

        $this->runWorker();

        // Check files are deleted in FS.
        foreach ($filePathes as $filePath) {
            static::assertFalse($this->filesystem->has($filePath));
        }
    }

    public function testExecuteWithSomeFilesExpired(): void
    {
        $num = 25;
        $data = $this->prepareImportExportFileTestData($num);
        $count = 0;
        $expiredIds = [];
        $filePathes = ['keep' => [], 'delete' => []];
        foreach (array_keys($data) as $key) {
            // set every second file expired.
            if ($count++ % 2 === 0) {
                $filePathes['delete'][] = $data[$key]['path'];
                $data[$key]['expireDate'] = date('Y-m-d H:i:s', strtotime('-1 second'));
                $expiredIds[] = $data[$key]['id'];
            } else {
                $filePathes['keep'][] = $data[$key]['path'];
            }
        }

        $this->fileRepository->create(array_values($data), $this->context);

        $commandTester = new CommandTester($this->deleteExpiredFilesCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $numExpired = \count($expiredIds);
        $message = $commandTester->getDisplay();
        static::assertMatchesRegularExpression(
            sprintf('/Are you sure that you want to delete %d expired files\? \(yes\/no\) \[no\]:/', $numExpired),
            $message
        );
        static::assertMatchesRegularExpression(sprintf('/\[OK\] Successfully deleted %d expired files./', $numExpired), $message);

        $results = $this->fileRepository->searchIds(new Criteria(), $this->context)->getIds();
        static::assertEquals(($num - $numExpired), \count($results));

        $expectedIds = array_diff(array_column($data, 'id'), $expiredIds);
        foreach ($results as $result) {
            static::assertContains($result, $expectedIds);
            static::assertNotContains($result, $expiredIds);
        }

        $this->runWorker();

        // Check files are deleted in FS.
        foreach ($filePathes as $expect => $files) {
            foreach ($files as $filePath) {
                if ($expect === 'keep') {
                    static::assertTrue($this->filesystem->has($filePath));
                } else {
                    static::assertFalse($this->filesystem->has($filePath));
                }
            }
        }
    }

    public function testExecuteWithAllFilesExpiredUserExits(): void
    {
        $num = 25;
        $data = $this->prepareImportExportFileTestData($num);
        foreach (array_keys($data) as $key) {
            $data[$key]['expireDate'] = date('Y-m-d H:i:s', strtotime('-1 second'));
        }

        $this->fileRepository->create(array_values($data), $this->context);

        $inputs = ['', 'no', 'foo', 'No', 'NO', 'N'];

        foreach ($inputs as $input) {
            $commandTester = new CommandTester($this->deleteExpiredFilesCommand);
            $commandTester->setInputs([$input]);
            $commandTester->execute([]);

            $message = $commandTester->getDisplay();
            static::assertMatchesRegularExpression(
                sprintf('/Are you sure that you want to delete %d expired files\? \(yes\/no\) \[no\]:/', $num),
                $message
            );
            static::assertMatchesRegularExpression('/\[CAUTION\] Aborting due to user input./', $message);
        }
    }

    /**
     * Prepare a defined number of test data.
     */
    protected function prepareImportExportFileTestData(int $num = 1, string $add = 'x'): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $filePath = 'import/' . ImportExportFileEntity::buildPath($uuid);

            $this->filesystem->write($filePath, 'foobar');
            static::assertTrue($this->filesystem->has($filePath));

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'originalName' => sprintf('file%d.xml', $i),
                'path' => $filePath,
                'expireDate' => date('Y-m-d H:i:s', (int) strtotime('+' . $i . ' day')),
                'size' => $i * 51,
                'accessToken' => Random::getBase64UrlString(32),
            ];
        }

        return $data;
    }
}
