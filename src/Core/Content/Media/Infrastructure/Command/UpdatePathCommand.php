<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Command;

use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
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
        private readonly IteratorFactory $factory
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

        $iterator = $this->factory->createIterator('media');
        if (!$input->getOption('force')) {
            $iterator->getQuery()->andWhere('path IS NULL');
        }

        $progressBar = new ProgressBar($output, $iterator->fetchCount());
        $progressBar->start();

        while ($ids = $iterator->fetch()) {
            $this->updater->updateMedia($ids);
            $progressBar->advance(\count($ids));
        }
        $progressBar->finish();

        $output->writeln('');
        $output->writeln('Updating thumbnail paths...');
        $output->writeln('');

        $iterator = $this->factory->createIterator('media_thumbnail');

        if (!$input->getOption('force')) {
            $iterator->getQuery()->andWhere('path IS NULL');
        }
        $progressBar = new ProgressBar($output, $iterator->fetchCount());

        $progressBar->start();
        while ($ids = $iterator->fetch()) {
            $this->updater->updateThumbnails($ids);
            $progressBar->advance(\count($ids));
        }
        $progressBar->finish();

        return 0;
    }
}
