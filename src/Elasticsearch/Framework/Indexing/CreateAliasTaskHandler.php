<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class CreateAliasTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        Client $client,
        Connection $connection,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->client = $client;
        $this->connection = $connection;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->logger = $logger;
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
            $this->logger->critical($e->getMessage());
        }
    }

    private function indexReady(string $index, string $entity, int $expected): bool
    {
        /** @var array $remote */
        $remote = $this->client->count([
            'index' => $index,
            'type' => $entity,
        ]);

        return $remote['count'] >= $expected;
    }

    private function createAlias(string $index, string $alias): void
    {
        $exist = $this->client->indices()->existsAlias(['name' => $alias]);

        if (!$exist) {
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
            $entity = $row['entity'];
            $count = (int) $row['doc_count'];

            $this->client->indices()->refresh(['index' => $index]);

            if (!$this->indexReady($index, $entity, $count)) {
                continue;
            }

            $alias = $row['alias'];

            $this->createAlias($index, $alias);

            $this->client->indices()->putSettings([
                'index' => $index,
                'body' => [
                    'number_of_replicas' => 3,
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
