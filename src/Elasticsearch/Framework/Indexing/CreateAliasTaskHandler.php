<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexAliasSwitchedEvent;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
class CreateAliasTaskHandler extends ScheduledTaskHandler
{
    private Client $client;

    private Connection $connection;

    private ElasticsearchHelper $elasticsearchHelper;

    /**
     * @var array<mixed>
     */
    private array $config;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     *
     * @param array<mixed> $config
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        Client $client,
        Connection $connection,
        ElasticsearchHelper $elasticsearchHelper,
        array $config,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->client = $client;
        $this->connection = $connection;
        $this->elasticsearchHelper = $elasticsearchHelper;
        $this->config = $config;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return iterable<class-string>
     */
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
            $this->elasticsearchHelper->logAndThrowException($e);
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
        $indices = $this->connection->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        if (empty($indices)) {
            return;
        }

        $changes = [];

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

            $this->connection->executeStatement(
                'DELETE FROM elasticsearch_index_task WHERE id = :id',
                ['id' => $row['id']]
            );

            $changes[(string) $index] = $alias;
        }

        $this->eventDispatcher->dispatch(new ElasticsearchIndexAliasSwitchedEvent($changes));
    }
}
