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

        $orphansOnly = $input->getOption('orphans') && !$input->getOption('force');

        if (!$this->remoteThumbnailsEnable && !$input->getOption('force') && !$orphansOnly) {
            $io->comment('Deleting thumbnails is only supported when remote thumbnail is enabled. Use the --force option to delete them anyway, --orphans to only delete files without a database record, or "media:generate-thumbnails --force" to regenerate them in place.');

            return self::FAILURE;
        }

        $thumbnails = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(`id`)) as id, `path` FROM `media_thumbnail`');

        $orphaned = $this->findOrphanedThumbnailFiles(array_values(array_filter($thumbnails)));

        if ($orphansOnly) {
            $this->deleteOrphanedThumbnailFiles($io, $orphaned, \count($thumbnails));

            $io->success('Successfully deleted all orphaned thumbnail files.');

            return self::SUCCESS;
        }

        $this->deleteThumbnails(array_keys($thumbnails));
        $this->deleteThumbnailFiles();

        $io->table(
            ['Deleted', 'Number of thumbnail files'],
            [
                ['Referenced', \count($thumbnails)],
                ['Orphaned', \count($orphaned)],
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
     * outdated cache buster path after their media was uploaded again.
     *
     * @param list<string> $recordPaths
     *
     * @return list<array{FilesystemOperator, string}>
     */
    private function findOrphanedThumbnailFiles(array $recordPaths): array
    {
        $recordPaths = array_flip($recordPaths);

        $orphaned = [];
        foreach ([$this->filesystemPublic, $this->filesystemPrivate] as $filesystem) {
            foreach ($filesystem->listContents('thumbnail', true) as $item) {
                if ($item->isFile() && !isset($recordPaths[$item->path()])) {
                    $orphaned[] = [$filesystem, $item->path()];
                }
            }
        }

        return $orphaned;
    }

    /**
     * @param list<array{FilesystemOperator, string}> $orphaned
     */
    private function deleteOrphanedThumbnailFiles(SymfonyStyle $io, array $orphaned, int $keptCount): void
    {
        foreach ($orphaned as [$filesystem, $path]) {
            $filesystem->delete($path);
        }

        $io->table(
            ['Action', 'Number of thumbnail files'],
            [
                ['Deleted (orphaned)', \count($orphaned)],
                ['Kept (referenced)', $keptCount],
            ]
        );
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
