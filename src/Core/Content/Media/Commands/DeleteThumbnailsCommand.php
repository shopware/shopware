<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('discovery')]
#[AsCommand(
    name: 'media:delete-local-thumbnails',
    description: 'Deletes all media thumbnail records and physical thumbnail files.',
)]
class DeleteThumbnailsCommand extends Command
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaThumbnailCollection> $thumbnailRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $thumbnailRepository,
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly bool $remoteThumbnailsEnable = false
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Delete thumbnails even when remote thumbnails are disabled. The storefront will be missing thumbnails until they are regenerated'
        );
        $this->addOption(
            'orphans',
            'o',
            InputOption::VALUE_NONE,
            'Only delete orphaned thumbnail files without a database record. Referenced thumbnails are kept, so this is safe in every setup'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $force = (bool) $input->getOption('force');
        $orphansOnly = (bool) $input->getOption('orphans');

        if ($force && $orphansOnly) {
            $io->error('The options --force and --orphans cannot be combined: --force deletes all thumbnail files including orphaned ones, while --orphans only deletes orphaned files.');

            return self::INVALID;
        }

        if (!$this->remoteThumbnailsEnable && !$force && !$orphansOnly) {
            $io->comment('Deleting thumbnails is only supported when remote thumbnail is enabled. Use the --force option to delete them anyway, --orphans to only delete files without a database record, or "media:generate-thumbnails --force" to regenerate them in place.');

            return self::FAILURE;
        }

        $thumbnails = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(`id`)) as id, `path` FROM `media_thumbnail`');

        if ($orphansOnly) {
            $deletedCount = $this->deleteOrphanedThumbnailFiles(array_values(array_filter($thumbnails)));

            $io->table(
                ['Action', 'Number of thumbnail files'],
                [
                    ['Deleted (orphaned)', $deletedCount],
                    ['Kept (referenced)', \count($thumbnails)],
                ]
            );

            $io->success('Successfully deleted all orphaned thumbnail files.');

            return self::SUCCESS;
        }

        $fileCount = $this->countThumbnailFiles();

        $this->deleteThumbnails(array_keys($thumbnails));
        $this->deleteThumbnailFiles();

        $io->table(
            ['Action', 'Number of thumbnail files'],
            [
                ['Deleted', $fileCount],
            ]
        );

        $io->success('Successfully deleted all thumbnails records and thumbnails files.');

        return self::SUCCESS;
    }

    /**
     * @param list<string> $thumbnailIds
     */
    private function deleteThumbnails(array $thumbnailIds): void
    {
        $ids = array_map(static fn (string $id) => ['id' => $id], $thumbnailIds);

        $this->thumbnailRepository->delete($ids, Context::createCLIContext());

        $this->connection->executeStatement('UPDATE `media` SET `thumbnails_ro` = NULL;');
    }

    /**
     * Orphaned files have no database record anymore, e.g. because they were left behind under an
     * outdated cache buster path after their media was uploaded again. Files are deleted while
     * iterating, so the tree is walked once and never materialized in memory.
     *
     * @param list<string> $recordPaths
     */
    private function deleteOrphanedThumbnailFiles(array $recordPaths): int
    {
        $recordPaths = array_flip($recordPaths);

        $deleted = 0;
        foreach ([$this->filesystemPublic, $this->filesystemPrivate] as $filesystem) {
            foreach ($filesystem->listContents('thumbnail', true) as $item) {
                if ($item->isFile() && !isset($recordPaths[$item->path()])) {
                    $filesystem->delete($item->path());
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }

    private function countThumbnailFiles(): int
    {
        $count = 0;
        foreach ([$this->filesystemPublic, $this->filesystemPrivate] as $filesystem) {
            foreach ($filesystem->listContents('thumbnail', true) as $item) {
                if ($item->isFile()) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Removes the whole physical thumbnail directory to also catch orphaned files.
     */
    private function deleteThumbnailFiles(): void
    {
        $this->filesystemPublic->deleteDirectory('thumbnail');
        $this->filesystemPrivate->deleteDirectory('thumbnail');
    }
}
