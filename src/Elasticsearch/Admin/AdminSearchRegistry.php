<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\OpenSearchException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('inventory')]
#[AsMessageHandler(handles: AdminSearchIndexingMessage::class)]
class AdminSearchRegistry implements EventSubscriberInterface
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * @param iterable<AbstractAdminIndexer> $indexer
     * @param array<string, mixed> $config
     * @param array<string, mixed> $mapping
     */
    public function __construct(
        private readonly iterable $indexer,
        private readonly Connection $connection,
        private readonly MessageBusInterface $queue,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Client $client,
        private readonly AdminElasticsearchHelper $adminEsHelper,
        private readonly LoggerInterface $logger,
        array $config,
        private readonly array $mapping,
        private readonly string $environment
    ) {
        if (isset($config['settings']['index'])) {
            if (\array_key_exists('number_of_shards', $config['settings']['index']) && $config['settings']['index']['number_of_shards'] === null) {
                unset($config['settings']['index']['number_of_shards']);
            }

            if (\array_key_exists('number_of_replicas', $config['settings']['index']) && $config['settings']['index']['number_of_replicas'] === null) {
                unset($config['settings']['index']['number_of_replicas']);
            }
        }

        $this->config = $config;
    }

    public function __invoke(AdminSearchIndexingMessage $message): void
    {
        $indexer = $this->getIndexer($message->getEntity());

        $this->push($indexer, $message);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['refresh', -1000],
            ],
        ];
    }

    public function iterate(AdminIndexingBehavior $indexingBehavior): void
    {
        if (!$this->adminEsHelper->isEnabled()) {
            return;
        }

        $indexers = $this->getIndexersArray();
        /** @var list<string> $entities */
        $entities = array_keys($indexers);

        if ($indexingBehavior->getOnlyEntities()) {
            $entities = array_intersect($entities, $indexingBehavior->getOnlyEntities());
        } elseif ($indexingBehavior->getSkipEntities()) {
            $entities = array_diff($entities, $indexingBehavior->getSkipEntities());
        }

        $indices = $this->createIndices($entities);

        foreach ($entities as $entityName) {
            $indexer = $indexers[$entityName];
            $iterator = $indexer->getIterator();

            $this->dispatcher->dispatch(new ProgressStartedEvent($indexer->getName(), $iterator->fetchCount()));

            while ($ids = $iterator->fetch()) {
                $ids = array_values($ids);

                // we provide no queue when the data is sent by the admin
                if ($indexingBehavior->getNoQueue()) {
                    $this->__invoke(new AdminSearchIndexingMessage($indexer->getEntity(), $indexer->getName(), $indices, $ids));
                } else {
                    $this->queue->dispatch(new AdminSearchIndexingMessage($indexer->getEntity(), $indexer->getName(), $indices, $ids));
                }

                $this->dispatcher->dispatch(new ProgressAdvancedEvent(\count($ids)));
            }

            $this->dispatcher->dispatch(new ProgressFinishedEvent($indexer->getName()));
        }

        $this->swapAlias($indices);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if (!$this->adminEsHelper->isEnabled() || !$this->isIndexedEntityWritten($event)) {
            return;
        }

        if ($this->adminEsHelper->getRefreshIndices()) {
            try {
                $this->refreshIndices();
            } catch (OpenSearchException $e) {
                $this->logger->error('Could not refresh indices. Run "bin/console es:admin:mapping:update" & "bin/console es:admin:index" to update indices and reindex. Error: ' . $e->getMessage());

                return;
            }
        }

        /** @var array<string, string> $indices */
        $indices = $this->connection->fetchAllKeyValue('SELECT `alias`, `index` FROM admin_elasticsearch_index_task');

        if (empty($indices)) {
            return;
        }

        foreach ($this->indexer as $indexer) {
            $ids = $indexer->getUpdatedIds($event);
            $deletedIds = $event->getDeletedPrimaryKeys($indexer->getEntity());
            $ids = array_values(array_diff($ids, $deletedIds));

            if ($ids === [] && $deletedIds === []) {
                continue;
            }

            $msg = new AdminSearchIndexingMessage($indexer->getEntity(), $indexer->getName(), $indices, $ids, $deletedIds);

            // if the event is triggered from storefront or sales channel API, we dispatch the message to the queue to not slow down the request
            if ($event->getContext()->getSource() instanceof SalesChannelApiSource) {
                $this->queue->dispatch($msg);

                return;
            }

            // otherwise we invoke the message handler directly
            $this->__invoke($msg);
        }
    }

    /**
     * @return iterable<AbstractAdminIndexer>
     */
    public function getIndexers(): iterable
    {
        return $this->indexer;
    }

    public function getIndexer(string $name): AbstractAdminIndexer
    {
        $indexers = $this->getIndexersArray();
        $indexer = $indexers[$name] ?? null;
        if ($indexer) {
            return $indexer;
        }

        throw ElasticsearchException::indexingError([\sprintf('Indexer for name %s not found', $name)]);
    }

    public function hasIndexer(string $name): bool
    {
        $indexers = $this->getIndexersArray();

        return isset($indexers[$name]);
    }

    public function updateMappings(): void
    {
        foreach ($this->indexer as $indexer) {
            $mapping = $this->buildMapping($indexer);

            $this->client->indices()->putMapping([
                'index' => $this->adminEsHelper->getIndex($indexer->getName()),
                'body' => $mapping,
            ]);
        }
    }

    private function isIndexedEntityWritten(EntityWrittenContainerEvent $event): bool
    {
        // only index entities that are written in the live version
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return false;
        }

        foreach ($this->indexer as $indexer) {
            $ids = $event->getPrimaryKeys($indexer->getEntity());

            if ($ids !== []) {
                return true;
            }
        }

        return false;
    }

    private function push(AbstractAdminIndexer $indexer, AdminSearchIndexingMessage $message): void
    {
        $indices = $message->getIndices();

        $ids = $message->getIds();
        $alias = $this->adminEsHelper->getIndex($indexer->getName());

        if (!isset($indices[$alias])) {
            return;
        }

        $data = $ids !== [] ? $indexer->fetch($ids) : [];
        $toRemove = array_filter($ids, static fn (string $id): bool => !isset($data[$id]));
        $toRemove = array_unique(array_merge($toRemove, $message->getToRemoveIds()));

        $documents = [];
        foreach ($data as $id => $document) {
            $documents[] = ['index' => ['_id' => $id]];

            $documents[] = \array_replace(
                ['entityName' => $indexer->getEntity(), 'parameters' => [], 'textBoosted' => '', 'text' => ''],
                $document
            );
        }

        foreach ($toRemove as $id) {
            $documents[] = ['delete' => ['_id' => $id]];
        }

        $arguments = [
            'index' => $indices[$alias],
            'body' => $documents,
        ];

        $result = $this->client->bulk($arguments);

        if (\is_array($result) && !empty($result['errors'])) {
            $errors = $this->parseErrors($result);

            throw ElasticsearchException::indexingError($errors);
        }
    }

    /**
     * @param array<string> $entities
     *
     * @throws Exception
     *
     * @return array<string, string>
     */
    private function createIndices(array $entities): array
    {
        $indexTasks = [];
        $indices = [];
        foreach ($entities as $entityName) {
            $indexer = $this->getIndexer($entityName);
            $alias = $this->adminEsHelper->getIndex($indexer->getName());
            $index = $alias . '_' . time();

            if ($this->client->indices()->exists(['index' => $index])) {
                continue;
            }

            $indices[$alias] = $index;

            $this->create($indexer, $index, $alias);

            $iterator = $indexer->getIterator();
            $indexTasks[] = [
                'id' => Uuid::randomBytes(),
                '`entity`' => $indexer->getEntity(),
                '`index`' => $index,
                '`alias`' => $alias,
                '`doc_count`' => $iterator->fetchCount(),
            ];
        }

        $this->connection->executeStatement(
            'DELETE FROM admin_elasticsearch_index_task WHERE `entity` IN (:entities)',
            ['entities' => $entities],
            ['entities' => ArrayParameterType::STRING]
        );

        foreach ($indexTasks as $task) {
            $this->connection->insert('admin_elasticsearch_index_task', $task);
        }

        return $indices;
    }

    private function refreshIndices(): void
    {
        $entities = [];
        $indexTasks = [];
        foreach ($this->indexer as $indexer) {
            $alias = $this->adminEsHelper->getIndex($indexer->getName());

            if ($this->client->indices()->existsAlias(['name' => $alias])) {
                continue;
            }

            $index = $alias . '_' . time();
            $this->create($indexer, $index, $alias);

            $entities[] = $indexer->getEntity();

            $iterator = $indexer->getIterator();
            $indexTasks[] = [
                'id' => Uuid::randomBytes(),
                '`entity`' => $indexer->getEntity(),
                '`index`' => $index,
                '`alias`' => $alias,
                '`doc_count`' => $iterator->fetchCount(),
            ];
        }

        $this->connection->executeStatement(
            'DELETE FROM admin_elasticsearch_index_task WHERE `entity` IN (:entities)',
            ['entities' => $entities],
            ['entities' => ArrayParameterType::STRING]
        );

        foreach ($indexTasks as $task) {
            $this->connection->insert('admin_elasticsearch_index_task', $task);
        }
    }

    private function create(AbstractAdminIndexer $indexer, string $index, string $alias): void
    {
        $mapping = $this->buildMapping($indexer);

        $body = array_merge(
            $this->config,
            ['mappings' => $mapping]
        );

        $this->client->indices()->create([
            'index' => $index,
            'body' => $body,
        ]);

        $this->createAliasIfNotExisting($index, $alias);
    }

    /**
     * @param array<string, array<array<string, mixed>>> $result
     *
     * @return array<array{reason: string}|string>
     */
    private function parseErrors(array $result): array
    {
        $errors = [];
        foreach ($result['items'] as $item) {
            $item = $item['index'] ?? $item['delete'];

            if (\in_array($item['status'], [200, 201], true)) {
                continue;
            }

            $errors[] = [
                'index' => $item['_index'],
                'id' => $item['_id'],
                'type' => $item['error']['type'] ?? $item['_type'],
                'reason' => $item['error']['reason'] ?? $item['result'],
            ];
        }

        return $errors;
    }

    private function createAliasIfNotExisting(string $index, string $alias): void
    {
        if ($this->client->indices()->existsAlias(['name' => $alias])) {
            return;
        }

        $this->putAlias($index, $alias);
    }

    /**
     * @param array<string, string> $indices
     */
    private function swapAlias(array $indices): void
    {
        foreach ($indices as $alias => $index) {
            if (!$this->client->indices()->existsAlias(['name' => $alias])) {
                $this->putAlias($index, $alias);

                return;
            }

            $current = $this->client->indices()->getAlias(['name' => $alias]);

            if (!isset($current[$index])) {
                $this->putAlias($index, $alias);
            }

            unset($current[$index]);
            $current = array_keys($current);

            foreach ($current as $value) {
                $this->client->indices()->delete(['index' => $value]);
            }
        }
    }

    private function putAlias(string $index, string $alias): void
    {
        $this->client->indices()->refresh([
            'index' => $index,
        ]);
        $this->client->indices()->putAlias(['index' => $index, 'name' => $alias]);
    }

    /**
     * @return array<mixed>
     */
    private function buildMapping(AbstractAdminIndexer $indexer): array
    {
        $properties = [
            'properties' => [
                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'textBoosted' => AbstractAdminIndexer::SEARCH_FIELD,
                'text' => AbstractAdminIndexer::SEARCH_FIELD,
                'entityName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'parameters' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ],
        ];

        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            $properties['properties']['textBoosted']['fields']['ngram']['search_analyzer'] = 'sw_whitespace_analyzer';
            $properties['properties']['text']['fields']['ngram']['search_analyzer'] = 'sw_whitespace_analyzer';
        }

        $mapping = $indexer->mapping($properties);

        $debug = $this->environment === 'dev' || $this->environment === 'test';

        if (!$debug) {
            $mapping['_source'] = ['includes' => ['id', 'text', 'textBoosted', 'entityName', 'parameters']];
        }

        return array_merge_recursive($mapping, $this->mapping);
    }

    /**
     * @return array<string, AbstractAdminIndexer>
     */
    private function getIndexersArray(): array
    {
        if ($this->indexer instanceof \Traversable) {
            return iterator_to_array($this->indexer);
        }

        return $this->indexer;
    }
}
