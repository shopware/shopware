<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'dal:refresh:index',
    description: 'Refreshes the index for a given entity',
)]
#[Package('core')]
class RefreshIndexCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityIndexerRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('use-queue', null, InputOption::VALUE_NONE, 'Ignore cache and force generation')
            ->addOption('skip', null, InputArgument::OPTIONAL, 'Comma separated list of indexer names to be skipped')
            ->addOption('only', null, InputArgument::OPTIONAL, 'Comma separated list of indexer names to be generated')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $skip = \is_string($input->getOption('skip')) ? explode(',', (string) $input->getOption('skip')) : [];
        $only = \is_string($input->getOption('only')) ? explode(',', (string) $input->getOption('only')) : [];

        $this->registry->index($input->getOption('use-queue'), $skip, $only);

        $skipEntities = array_map(fn ($indexer) => str_replace('.indexer', '', (string) $indexer), $skip);

        $onlyEntities = array_map(fn ($indexer) => str_replace('.indexer', '', (string) $indexer), $only);

        $event = new RefreshIndexEvent(!$input->getOption('use-queue'), $skipEntities, $onlyEntities);
        $this->eventDispatcher->dispatch($event);

        return self::SUCCESS;
    }
}
