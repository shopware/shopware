<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskHandler;

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

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        Client $client,
        Connection $connection
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->client = $client;
        $this->connection = $connection;
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
        $indices = $this->connection->fetchAll('SELECT * FROM elasticsearch_indexing');

        if (empty($indices)) {
            return;
        }

        foreach ($indices as $row) {
            $index = $row['index'];
            $entity = $row['entity'];
            $count = $row['doc_count'];

            if (!$this->indexReady($index, $entity, $count)) {
                continue;
            }

            $alias = $row['alias'];

            $this->createAlias($index, $alias);

            $this->client->indices()->refresh(['index' => $index]);

            $this->connection->executeUpdate(
                'DELETE FROM elasticsearch_indexing WHERE id = :id',
                ['id' => $row['id']]
            );
        }
    }
}
