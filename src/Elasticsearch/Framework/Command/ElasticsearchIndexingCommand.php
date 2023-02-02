<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ElasticsearchIndexingCommand extends Command
{
    use ConsoleProgressTrait;

    protected static $defaultName = 'es:index';

    private ElasticsearchIndexer $indexer;

    private MessageBusInterface $messageBus;

    private CreateAliasTaskHandler $aliasHandler;

    /**
     * @internal
     */
    public function __construct(ElasticsearchIndexer $indexer, MessageBusInterface $messageBus, CreateAliasTaskHandler $aliasHandler)
    {
        parent::__construct();
        $this->indexer = $indexer;
        $this->messageBus = $messageBus;
        $this->aliasHandler = $aliasHandler;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Reindex all entities to elasticsearch')
            ->addOption('no-queue', null, null, 'Do not use the queue for indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        $offset = null;
        while ($message = $this->indexer->iterate($offset)) {
            $offset = $message->getOffset();

            $step = \count($message->getData()->getIds());

            if ($input->getOption('no-queue')) {
                $this->indexer->handle($message);

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

        return self::SUCCESS;
    }
}
