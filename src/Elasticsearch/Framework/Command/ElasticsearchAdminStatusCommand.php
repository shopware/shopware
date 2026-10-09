<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('inventory')]
#[AsCommand(
    name: 'es:admin:status',
    description: 'Show the status of the admin elasticsearch indices',
)]
class ElasticsearchAdminStatusCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly Connection $connection,
        private readonly AdminElasticsearchHelper $adminEsHelper
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->adminEsHelper->isEnabled() !== true) {
            $io->error('Admin elasticsearch is not enabled');

            return self::FAILURE;
        }

        if (!$this->client->ping()) {
            throw ElasticsearchException::serverNotAvailable();
        }

        $health = $this->client->cluster()->health();

        $tasks = $this->connection->fetchAllAssociative(
            'SELECT `entity`, `index`, `alias`, `doc_count` FROM admin_elasticsearch_index_task ORDER BY `entity`'
        );

        $pending = array_filter($tasks, static fn (array $task) => (int) $task['doc_count'] > 0);

        $table = new Table($output);
        $table->setHeaders(['Name', 'Status']);
        $table->addRow(['Cluster Status', $health['status']]);
        $table->addRow(['Available Nodes', $health['number_of_nodes']]);
        $table->addRow(['Indexing', $pending === [] ? 'completed' : 'in progress']);
        $table->render();
        $output->writeln('');

        if ($tasks === []) {
            $io->warning('No admin indices have been built yet. Run "bin/console es:admin:index" to create them.');

            return self::SUCCESS;
        }

        $live = $this->getLiveIndices(array_unique(array_map(static fn (array $task) => (string) $task['alias'], $tasks)));

        $taskTable = new Table($output);
        $taskTable->setHeaders(['Entity', 'Index', 'Alias', 'Remaining documents', 'Status']);

        foreach ($tasks as $task) {
            $remaining = (int) $task['doc_count'];

            if ($remaining > 0) {
                $status = 'indexing';
            } elseif (\in_array((string) $task['index'], $live, true)) {
                $status = 'live';
            } else {
                $status = 'waiting for alias swap';
            }

            $taskTable->addRow([
                $task['entity'],
                $task['index'],
                $task['alias'],
                max($remaining, 0),
                $status,
            ]);
        }

        $taskTable->render();
        $output->writeln('');

        return self::SUCCESS;
    }

    /**
     * @param array<string> $aliases
     *
     * @return array<string> indices currently serving one of the given aliases
     */
    private function getLiveIndices(array $aliases): array
    {
        $live = [];

        foreach ($aliases as $alias) {
            if (!$this->client->indices()->existsAlias(['name' => $alias])) {
                continue;
            }

            $live = array_merge($live, array_keys($this->client->indices()->getAlias(['name' => $alias])));
        }

        return $live;
    }
}
