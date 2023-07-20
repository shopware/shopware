<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'media:update-path',
    description: 'Iterates over the media and updates the path column.',
)]
#[Package('content')]
class UpdatePath extends Command
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
        $this->setDescription('Update media paths');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Updating media paths...');

        $iterator = $this->factory->createIterator('media');

        $progressBar = new ProgressBar($output, $iterator->fetchCount());
        $progressBar->start();

        while ($ids = $iterator->fetch()) {
            $this->updater->updateMedia($ids);
            $progressBar->advance(count($ids));
        }
        $progressBar->finish();

        $output->writeln('');
        $output->writeln('Updating thumbnail paths...');
        $output->writeln('');

        $iterator = $this->factory->createIterator('media_thumbnail');
        $progressBar = new ProgressBar($output, $iterator->fetchCount());

        $progressBar->start();
        while ($ids = $iterator->fetch()) {
            $this->updater->updateThumbnails($ids);
            $progressBar->advance(count($ids));
        }
        $progressBar->finish();

        return 0;
    }
}
