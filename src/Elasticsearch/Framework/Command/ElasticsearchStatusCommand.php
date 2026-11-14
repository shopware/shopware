<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(
    name: 'es:status',
    description: 'Show the status of the elasticsearch index',
)]
class ElasticsearchStatusCommand extends Command
{
    /**
     * @deprecated tag:v6.8.0 - The command no longer renders a progress bar, so the trait and the
     * getSubscribedEvents(), startProgress(), advanceProgress() and finishProgress() methods it provides will be
     * removed. They are kept for one minor because the class is not internal.
     */
    use ConsoleProgressTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->client->ping()) {
            throw ElasticsearchException::serverNotAvailable();
        }

        $health = $this->client->cluster()->health();

        $tasks = $this->connection->fetchAllAssociative(
            'SELECT `entity`, `index`, `alias`, `doc_count` FROM elasticsearch_index_task ORDER BY `entity`'
        );

        $table = new Table($output);
        $table->setHeaders(['Name', 'Status']);
        $table->addRow(['Cluster Status', $health['status']]);
        $table->addRow(['Available Nodes', $health['number_of_nodes']]);
        $table->addRow(['Indexing', $tasks === [] ? 'completed' : 'in progress']);
        $table->render();
        $output->writeln('');

        if ($tasks === []) {
            return self::SUCCESS;
        }

        // A row only exists while an index is being built: the alias is swapped and the row removed once the
        // remaining document count reaches zero.
        $taskTable = new Table($output);
        $taskTable->setHeaders(['Entity', 'Index', 'Alias', 'Remaining documents', 'Status']);

        foreach ($tasks as $task) {
            $remaining = (int) $task['doc_count'];

            $taskTable->addRow([
                $task['entity'],
                $task['index'],
                $task['alias'],
                max($remaining, 0),
                $remaining > 0 ? 'indexing' : 'waiting for alias swap',
            ]);
        }

        $taskTable->render();
        $output->writeln('');

        return self::SUCCESS;
    }
}
