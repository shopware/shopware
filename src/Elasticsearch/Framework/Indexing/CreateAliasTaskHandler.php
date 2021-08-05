<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class CreateAliasTaskHandler extends ScheduledTaskHandler
{
    private Client $client;

    private Connection $connection;

    private ElasticsearchHelper $elasticsearchHelper;

    private array $config;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        Client $client,
        Connection $connection,
        ElasticsearchHelper $elasticsearchHelper,
        array $config
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->client = $client;
        $this->connection = $connection;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->elasticsearchHelper = $elasticsearchHelper;
        $this->config = $config;
    }

    public static function getHandledMessages(): iterable
    {
        return [CreateAliasTask::class];
    }

    public function run(): void
    {
        try {
            $this->handleQueue();
        } catch (\Throwable $e) {
            // catch exception - otherwise the task will never be called again
            $this->elasticsearchHelper->logOrThrowException($e);
        }
    }

    private function createAlias(string $index, string $alias): void
    {
        $exist = $this->client->indices()->existsAlias(['name' => $alias]);

        if (!$exist) {
            $this->client->indices()->refresh([
                'index' => $index,
            ]);
            $this->client->indices()->putAlias(['index' => $index, 'name' => $alias]);

            return;
        }

        $actions = [
            ['add' => ['index' => $index, 'alias' => $alias]],
        ];

        $current = $this->client->indices()->getAlias(['name' => $alias]);
        $current = array_keys($current);

        foreach ($current as $value) {
            $actions[] = ['remove' => ['index' => $value, 'alias' => $alias]];
        }

        $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
    }

    private function handleQueue(): void
    {
        $indices = $this->connection->fetchAll('SELECT * FROM elasticsearch_index_task');
        if (empty($indices)) {
            return;
        }

        foreach ($indices as $row) {
            $index = $row['index'];
            $count = (int) $row['doc_count'];

            $this->client->indices()->refresh(['index' => $index]);

            if ($count > 0) {
                continue;
            }

            $alias = $row['alias'];

            $this->createAlias($index, $alias);

            $this->client->indices()->putSettings([
                'index' => $index,
                'body' => [
                    'number_of_replicas' => $this->config['settings']['index']['number_of_replicas'],
                    'refresh_interval' => null,
                ],
            ]);

            $this->connection->executeUpdate(
                'DELETE FROM elasticsearch_index_task WHERE id = :id',
                ['id' => $row['id']]
            );
        }
    }
}
