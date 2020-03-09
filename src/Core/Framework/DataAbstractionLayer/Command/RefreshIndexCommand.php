<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefreshIndexCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    protected static $defaultName = 'dal:refresh:index';

    /**
     * @var IndexerRegistryInterface
     */
    private $indexer;

    /**
     * @var EntityIndexerRegistry
     */
    private $entityIndexerRegistry;

    public function __construct(IndexerRegistryInterface $indexer, EntityIndexerRegistry $entityIndexerRegistry)
    {
        parent::__construct();
        $this->indexer = $indexer;
        $this->entityIndexerRegistry = $entityIndexerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Refreshes the shop indices')
            ->addOption('use-queue', null, InputOption::VALUE_NONE, 'Ignore cache and force generation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $this->indexer->index(new \DateTime());

        $this->entityIndexerRegistry->index(
            (bool) $input->getOption('use-queue')
        );

        return 0;
    }
}
