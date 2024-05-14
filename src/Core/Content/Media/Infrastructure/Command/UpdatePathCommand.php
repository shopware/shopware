<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'media:update-path',
    description: 'Iterates over the media and updates the path column.',
)]
#[Package('content')]
class UpdatePathCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaPathUpdater $updater,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Update media paths')
            ->addOption('force', 'f', null, 'Force update of all media paths');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Updating media paths...');

        if ($input->getOption('force')) {
            $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM media');
        } else {
            $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM media WHERE path IS NULL');
        }

        $progressBar = new ProgressBar($output, \count($ids));
        $progressBar->start();

        $chunks = array_chunk($ids, 200);
        foreach ($chunks as $ids) {
            $this->updater->updateMedia($ids);
            $progressBar->advance(\count($ids));
        }

        $progressBar->finish();

        $output->writeln('');
        $output->writeln('Updating thumbnail paths...');
        $output->writeln('');

        if ($input->getOption('force')) {
            $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM media_thumbnail');
        } else {
            $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM media_thumbnail WHERE path IS NULL');
        }

        $progressBar = new ProgressBar($output, \count($ids));

        $progressBar->start();
        $chunks = array_chunk($ids, 200);
        foreach ($chunks as $ids) {
            $this->updater->updateThumbnails($ids);
            $progressBar->advance(\count($ids));
        }
        $progressBar->finish();

        return 0;
    }
}
