<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchStartEvent;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'media:delete-unused',
    description: 'Deletes all media files which are not used in any entity',
)]
#[Package('buyers-experience')]
class DeleteNotUsedMediaCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly UnusedMediaPurger $unusedMediaPurger,
        private readonly EventDispatcherInterface $eventDispatcher,
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
        $this->addOption('report', description: 'Generate a list of files to be deleted');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        if ($input->getOption('report') && $input->getOption('dry-run')) {
            $io->error('The options --report and --dry-run cannot be used together, pick one or the other.');

            return self::FAILURE;
        }

        if ($input->getOption('report')) {
            return $this->report($input, $output);
        }

        if ($input->getOption('dry-run')) {
            return $this->dryRun($input, $output);
        }

        $confirm = $io->confirm('Are you sure that you want to delete unused media files?', false);

        if (!$confirm) {
            $io->caution('Aborting due to user input.');

            return self::SUCCESS;
        }

        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : 50;

        $listener = new class($io, $limit) {
            private int $steps = 0;

            private ?ProgressBar $progressBar = null;

            private int $totalMediaDeletionCandidates = 0;

            public function __construct(
                private ShopwareStyle $io,
                private int $limit,
            ) {
            }

            public function start(UnusedMediaSearchStartEvent $event): void
            {
                $this->totalMediaDeletionCandidates = $event->totalMediaDeletionCandidates;
                $this->io->note(\sprintf('Out of a total of %d media items there are %d candidates for removal', $event->totalMedia, $event->totalMediaDeletionCandidates));
                $this->progressBar = $this->io->createProgressBar($event->totalMediaDeletionCandidates);
                $this->progressBar->setFormat('debug');
                $this->progressBar->start();
            }

            public function advance(UnusedMediaSearchEvent $event): void
            {
                \assert($this->progressBar instanceof ProgressBar);

                $advance = $this->limit;
                if (($this->steps + $advance) > $this->totalMediaDeletionCandidates) {
                    $advance = $this->totalMediaDeletionCandidates - $this->steps;
                }

                $this->progressBar->advance($advance);
                $this->steps += $advance;

                // finished
                if ($this->steps === $this->totalMediaDeletionCandidates) {
                    $this->progressBar->finish();
                    $this->io->newLine(2);
                }
            }
        };

        $this->eventDispatcher->addListener(UnusedMediaSearchStartEvent::class, $listener->start(...));
        $this->eventDispatcher->addListener(UnusedMediaSearchEvent::class, $listener->advance(...), -1);

        $count = $this->unusedMediaPurger->deleteNotUsedMedia(
            $limit,
            $input->getOption('offset') !== null ? (int) $input->getOption('offset') : null,
            (int) $input->getOption('grace-period-days'),
            $input->getOption('folder-entity'),
        );

        if ($count === 0) {
            $io->success(\sprintf('There are no unused media files uploaded before the grace period of %d days.', (int) $input->getOption('grace-period-days')));

            return self::SUCCESS;
        }

        $io->success(\sprintf('Successfully deleted %d media files.', $count));

        return self::SUCCESS;
    }

    private function report(InputInterface $input, OutputInterface $output): int
    {
        $mediaBatches = $this->unusedMediaPurger->getNotUsedMedia(
            $input->getOption('limit') ? (int) $input->getOption('limit') : 50,
            $input->getOption('offset') ? (int) $input->getOption('offset') : null,
            (int) $input->getOption('grace-period-days'),
            $input->getOption('folder-entity'),
        );

        $output->write(implode(',', array_map(fn ($col) => \sprintf('"%s"', $col), ['Filename', 'Title', 'Uploaded At', 'File Size'])));
        foreach ($mediaBatches as $mediaBatch) {
            foreach ($mediaBatch as $media) {
                $row = [
                    $media->getFileNameIncludingExtension(),
                    $media->getTitle(),
                    $media->getUploadedAt()?->format('F jS, Y'),
                    MemorySizeCalculator::formatToBytes($media->getFileSize() ?? 0),
                ];

                $output->write(\sprintf("\n%s", implode(',', array_map(fn ($col) => \sprintf('"%s"', $col), $row))));
            }
        }

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
                // we only clear the screen when we actually have some unused media
                $cursor->clearScreen();
            }

            $totalCount += \count($medias);

            $cursor->moveToPosition(0, 0);
            $cursor->clearOutput();
            $io->title(
                \sprintf(
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
                // last batch
                return true;
            }

            return $io->confirm('Show next page?', false);
        });

        if ($totalCount === 0) {
            $io->success(\sprintf('There are no unused media files uploaded before the grace period of %d days.', (int) $input->getOption('grace-period-days')));
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

        // last remaining batch
        if (\count($batch) > 0) {
            return $callback($i++, $batch);
        }

        return true;
    }
}
