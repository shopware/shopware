<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'es:index',
    description: 'Index all entities into elasticsearch',
)]
#[Package('core')]
class ElasticsearchIndexingCommand extends Command
{
    use ConsoleProgressTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
        private readonly MessageBusInterface $messageBus,
        private readonly CreateAliasTaskHandler $aliasHandler,
        private readonly bool $enabled
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('no-queue', null, null, 'Do not use the queue for indexing');
        $this->addOption('only', null, InputOption::VALUE_REQUIRED, 'Add entities separated by comma to indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('es-indexing');
        $this->io = new ShopwareStyle($input, $output);

        if (!$this->enabled) {
            $this->io->error('Elasticsearch indexing is disabled');

            return self::FAILURE;
        }

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        $entities = $input->getOption('only') ? explode(',', $input->getOption('only')) : [];
        $offset = null;
        while ($message = $this->indexer->iterate($offset, $entities)) {
            $offset = $message->getOffset();

            $step = \count($message->getData()->getIds());

            if ($input->getOption('no-queue')) {
                $this->indexer->__invoke($message);

                $progressBar->advance($step);

                continue;
            }

            $this->messageBus->dispatch($message);

            $progressBar->advance($step);
        }

        $progressBar->finish();

        if ($input->getOption('no-queue')) {
            $this->aliasHandler->run();
        }

        $event = (string) $stopwatch->stop('es-indexing');

        $this->io->info($event);

        return self::SUCCESS;
    }
}
