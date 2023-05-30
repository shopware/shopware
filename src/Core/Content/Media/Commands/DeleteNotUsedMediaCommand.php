<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'media:delete-unused',
    description: 'Deletes all media files which are not used in any entity',
)]
#[Package('content')]
class DeleteNotUsedMediaCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly UnusedMediaPurger $unusedMediaPurger,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('folder-entity', null, InputOption::VALUE_REQUIRED, 'Restrict deletion of not used media in default location folders of the provided entity name');
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'The limit of media entries to query');
        $this->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'The offset to start from');
        $this->addOption('grace-period-days', null, InputOption::VALUE_REQUIRED, 'The offset to start from', 20);
        $this->addOption('dry-run', description: 'Show list of files to be deleted');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        try {
            $this->connection->fetchOne('SELECT JSON_OVERLAPS(JSON_ARRAY(1), JSON_ARRAY(1));');
        } catch (\Exception $e) {
            $io->error('Your database does not support the JSON_OVERLAPS function. Please update your database to MySQL 8.0 or MariaDB 10.9 or higher.');

            return self::FAILURE;
        }

        if ($input->getOption('dry-run')) {
            return $this->dryRun($input, $output);
        }

        $confirm = $io->confirm('Are you sure that you want to delete unused media files?', false);

        if (!$confirm) {
            $io->caution('Aborting due to user input.');

            return self::SUCCESS;
        }

        $count = $this->unusedMediaPurger->deleteNotUsedMedia(
            $input->getOption('limit') ? (int) $input->getOption('limit') : null,
            $input->getOption('offset') ? (int) $input->getOption('offset') : null,
            (int) $input->getOption('grace-period-days'),
            $input->getOption('folder-entity'),
        );

        if ($count === 0) {
            $io->success(sprintf('There are no unused media files uploaded before the grace period of %d days.', (int) $input->getOption('grace-period-days')));

            return self::SUCCESS;
        }

        $io->success(sprintf('Successfully deleted %d media files.', $count));

        return self::SUCCESS;
    }

    private function dryRun(InputInterface $input, OutputInterface $output): int
    {
        $cursor = new Cursor($output);

        $io = new ShopwareStyle($input, $output);

        $mediaBatches = $this->unusedMediaPurger->getNotUsedMedia(
            $input->getOption('limit') ? (int) $input->getOption('limit') : 50,
            $input->getOption('offset') ? (int) $input->getOption('offset') : null,
            (int) $input->getOption('grace-period-days'),
            $input->getOption('folder-entity'),
        );

        $totalCount = 0;
        $finished = $this->consumeGeneratorInBatches($mediaBatches, 20, function ($batchNum, array $medias) use ($io, $cursor, &$totalCount) {
            if ($batchNum === 0 && \count($medias) === 0) {
                return true;
            }

            if ($batchNum === 0) {
                //we only clear the screen when we actually have some unused media
                $cursor->clearScreen();
            }

            $totalCount += \count($medias);

            $cursor->moveToPosition(0, 0);
            $cursor->clearOutput();
            $io->title(
                sprintf(
                    'Files that will be deleted: Page %d. Records: %d - %d',
                    $batchNum + 1,
                    ($batchNum * 20) + 1,
                    $batchNum * 20 + \count($medias)
                )
            );

            $io->table(
                ['Filename', 'Title', 'Uploaded At', 'File Size'],
                array_map(
                    fn (MediaEntity $media) => [
                        $media->getFileNameIncludingExtension(),
                        $media->getTitle(),
                        $media->getUploadedAt()?->format('F jS, Y'),
                        MemorySizeCalculator::formatToBytes($media->getFileSize() ?? 0),
                    ],
                    $medias
                )
            );

            if (\count($medias) < 20) {
                //last batch
                return true;
            }

            return $io->confirm('Show next page?', false);
        });

        if ($totalCount === 0) {
            $io->success(sprintf('There are no unused media files uploaded before the grace period of %d days.', (int) $input->getOption('grace-period-days')));
        } elseif ($finished) {
            $io->success('No more files to show.');
        } else {
            $io->info('Aborting.');
        }

        return self::SUCCESS;
    }

    /**
     * Given a generator which yields arrays of items, this method will consume the generator in batches of the given size.
     *
     * @param callable(int, array<mixed>): bool $callback
     */
    private function consumeGeneratorInBatches(\Generator $generator, int $batchSize, callable $callback): bool
    {
        $i = 0;
        $batch = [];
        foreach ($generator as $items) {
            $batch = array_merge($batch, $items);

            while (\count($batch) >= $batchSize) {
                $continue = $callback($i++, array_splice($batch, 0, $batchSize));

                if (!$continue) {
                    return false;
                }
            }
        }

        //last remaining batch
        if (\count($batch) > 0) {
            return $callback($i++, $batch);
        }

        return true;
    }
}
