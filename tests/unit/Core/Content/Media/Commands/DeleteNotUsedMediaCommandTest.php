<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\DeleteNotUsedMediaCommand;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(DeleteNotUsedMediaCommand::class)]
class DeleteNotUsedMediaCommandTest extends TestCase
{
    public function testExecuteWithConfirm(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $service->expects(static::once())
            ->method('deleteNotUsedMedia')
            ->willReturn(2);

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Are you sure that you want to delete unused media files?', $commandTester->getDisplay());
        static::assertStringContainsString('Successfully deleted 2 media files.', $commandTester->getDisplay());
    }

    #[DataProvider('limitOffsetProvider')]
    public function testExecuteWithLimitAndOffset(int $limit, int $offset): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $service->expects(static::once())
            ->method('deleteNotUsedMedia')
            ->with($limit, static::identicalTo($offset))
            ->willReturn(2);

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--limit' => $limit, '--offset' => $offset]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Are you sure that you want to delete unused media files?', $commandTester->getDisplay());
        static::assertStringContainsString('Successfully deleted 2 media files.', $commandTester->getDisplay());
    }

    /**
     * @return array<string, array{0: int, 1:int}>
     */
    public static function limitOffsetProvider(): array
    {
        return [
            'zero-offset' => [10, 0],
            'mid-offset' => [10, 5],
        ];
    }

    public function testExecuteWithoutConfirmDoesNotPerformDelete(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $service->expects(static::never())
            ->method('deleteNotUsedMedia');

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Are you sure that you want to delete unused media files?', $commandTester->getDisplay());
        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testExecuteWithFolderEntityRestriction(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $service->expects(static::once())
            ->method('deleteNotUsedMedia')
            ->with(50, null, 20, 'product')
            ->willReturn(2);

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--folder-entity' => 'product']);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Are you sure that you want to delete unused media files?', $commandTester->getDisplay());
        static::assertStringContainsString('Successfully deleted 2 media files.', $commandTester->getDisplay());
    }

    public function testDryRunPrintsOutFilesToBeDeletedButDoesNotPerformDelete(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $result = function (): \Generator {
            yield [$this->createMedia('File 1')];
            yield [$this->createMedia('File 2')];
        };

        $service->expects(static::once())
            ->method('getNotUsedMedia')
            ->willReturnCallback($result);

        $service->expects(static::never())
            ->method('deleteNotUsedMedia');

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--dry-run' => true]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Files that will be deleted', $commandTester->getDisplay());
        static::assertMatchesRegularExpression(
            '#\s+File 1.jpg\s+File 1 title\s+February 16th, 2023\s+1 MB#',
            $commandTester->getDisplay()
        );
        static::assertMatchesRegularExpression(
            '#\s+File 2.jpg\s+File 2 title\s+February 16th, 2023\s+1 MB#',
            $commandTester->getDisplay()
        );
    }

    public function testDryRunPagination(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $generator = $this->generatorOfMedia([10, 11]);

        $service->expects(static::once())
            ->method('getNotUsedMedia')
            ->willReturnCallback($generator);

        $service->expects(static::never())
            ->method('deleteNotUsedMedia');

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--dry-run' => true]);

        $commandTester->assertCommandIsSuccessful();

        static::assertMatchesRegularExpression(
            $this->buildTableRegex(21),
            $commandTester->getDisplay()
        );
    }

    public function testDryRunPaginationCancelAfterFirstPage(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $generator = $this->generatorOfMedia([20, 20]);

        $service->expects(static::once())
            ->method('getNotUsedMedia')
            ->willReturnCallback($generator);

        $service->expects(static::never())
            ->method('deleteNotUsedMedia');

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute(['--dry-run' => true]);

        $commandTester->assertCommandIsSuccessful();

        static::assertMatchesRegularExpression(
            $this->buildTableRegex(20, true),
            $commandTester->getDisplay()
        );
    }

    public function testErrorIsReportedIfIncompatibleOptionsPassed(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dry-run' => true, '--report' => true]);

        $output = new BufferedOutput();

        $io = new SymfonyStyle(
            new ArrayInput([]),
            $output,
        );

        $io->error('The options --report and --dry-run cannot be used together, pick one or the other.');

        static::assertStringContainsString($output->fetch(), $commandTester->getDisplay());
    }

    public function testCsvOutput(): void
    {
        $service = $this->createMock(UnusedMediaPurger::class);

        $result = function (): \Generator {
            yield [$this->createMedia('File 1')];
            yield [$this->createMedia('File 2')];
        };

        $service->expects(static::once())
            ->method('getNotUsedMedia')
            ->willReturnCallback($result);

        $service->expects(static::never())
            ->method('deleteNotUsedMedia');

        $command = new DeleteNotUsedMediaCommand($service, $this->createMock(EventDispatcherInterface::class));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--report' => true]);

        $commandTester->assertCommandIsSuccessful();

        static::assertSame(
            [
                [
                    'Filename',
                    'Title',
                    'Uploaded At',
                    'File Size',
                ],
                [
                    'File 1.jpg',
                    'File 1 title',
                    'February 16th, 2023',
                    '1 MB',
                ],
                [
                    'File 2.jpg',
                    'File 2 title',
                    'February 16th, 2023',
                    '1 MB',
                ],
            ],
            array_map(str_getcsv(...), explode("\n", $commandTester->getDisplay(true)))
        );
    }

    /**
     * This method builds a naive regex to check that each table contains the correct amount of files
     *  and whether the continue/abort behaviour works.
     */
    private function buildTableRegex(int $numFiles, bool $addAbortMessage = false): string
    {
        $regex = '#^';
        $pages = 1;
        $lastPage = (int) ceil($numFiles / 20);

        for ($i = 1; $i <= $numFiles; ++$i) {
            if (($i - 1) % 20 === 0) {
                $from = (($pages - 1) * 20) + 1;
                $to = $pages * 20;

                if ($pages === $lastPage) {
                    $to = $numFiles;
                }

                $regex .= ".*Files that will be deleted: Page {$pages}. Records: {$from} - {$to}[\S\s]+?";
                ++$pages;
            }

            $regex .= "File {$i}.jpg[\S\s]+?";
        }

        if ($addAbortMessage) {
            $regex .= "\[INFO\] Aborting.[\s]+$";
        } else {
            $regex .= "\[OK\] No more files to show.[\s]+$";
        }

        return $regex . '#mi';
    }

    /**
     * @param array<int> $batches
     *
     * @return callable(): \Generator
     */
    private function generatorOfMedia(array $batches): callable
    {
        return function () use ($batches): \Generator {
            $counter = 1;
            foreach ($batches as $batch) {
                $medias = [];

                for ($j = 0; $j < $batch; ++$j) {
                    $medias[] = $this->createMedia('File ' . $counter++);
                }

                yield $medias;
            }
        };
    }

    private function createMedia(string $name): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier(Uuid::randomHex());
        $media->setFileName($name);
        $media->setFileExtension('jpg');
        $media->setTitle($name . ' title');
        $media->setUploadedAt(new \DateTime('16-02-2023 10:00'));
        $media->setFileSize(1024 * 1024);

        return $media;
    }
}
