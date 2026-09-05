<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use OpenSearch\Client;
use OpenSearch\Exception\OpenSearchExceptionInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
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
     * @var array<string, AbstractAdminIndexer>|null
     */
    private ?array $indexers = null;

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
        private readonly string $environment,
        private readonly ClockInterface $clock,
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
        if ($indexers === []) {
            return;
        }

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

                $message = new AdminSearchIndexingMessage($indexer->getEntity(), $indexer->getName(), $indices, $ids, [], true);

                // we provide no queue when the data is sent by the admin
                if ($indexingBehavior->getNoQueue()) {
                    $this->__invoke($message);
                } else {
                    $this->queue->dispatch($message);
                }

                $this->dispatcher->dispatch(new ProgressAdvancedEvent(\count($ids)));
            }

            $this->dispatcher->dispatch(new ProgressFinishedEvent($indexer->getName()));
        }

        // when the messages were handled inline the indices are complete already, so their aliases swap right here.
        // Queued runs are still being written to and are promoted by AdminCreateAliasTask once they finish.
        $this->swapFinishedAliases();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if (!$this->adminEsHelper->isEnabled() || !$this->isIndexedEntityWritten($event)) {
            return;
        }

        $indexers = $this->getIndexersArray();
        if ($indexers === []) {
            return;
        }

        if ($this->adminEsHelper->getRefreshIndices()) {
            try {
                $this->refreshIndices();
            } catch (ClientExceptionInterface|OpenSearchExceptionInterface $e) {
                $this->logger->error('Could not refresh indices. Run "bin/console es:admin:mapping:update" & "bin/console es:admin:index" to update indices and reindex. Error: ' . $e->getMessage());

                return;
            }
        }

        $targets = $this->getWriteTargets();
        if ($targets === []) {
            return;
        }

        $isSalesChannelSource = $event->getContext()->getSource() instanceof SalesChannelApiSource;

        foreach ($indexers as $indexer) {
            $ids = $indexer->getUpdatedIds($event);
            $deletedIds = $event->getDeletedPrimaryKeys($indexer->getEntity());
            $ids = array_values(array_diff($ids, $deletedIds));

            if ($ids === [] && $deletedIds === []) {
                continue;
            }

            $alias = $this->adminEsHelper->getIndex($indexer->getName());

            // while an indexing run is in progress an entity has two indices: the one the alias currently serves and
            // the one being built. Both receive the update, otherwise it is either invisible until the alias swaps
            // or discarded with the index it was written to.
            foreach ($targets[$alias] ?? [] as $index) {
                $msg = new AdminSearchIndexingMessage($indexer->getEntity(), $indexer->getName(), [$alias => $index], $ids, $deletedIds);

                // if the event is triggered from storefront or sales channel API, we dispatch the message to the queue to not slow down the request
                if ($isSalesChannelSource) {
                    $this->queue->dispatch($msg);

                    continue;
                }

                // otherwise we invoke the message handler directly
                $this->__invoke($msg);
            }
        }
    }

    /**
     * Promotes every index that finished its indexing run to its alias and removes the index it replaces. Indices
     * that are still being written to keep a remaining document count above zero and are left alone.
     */
    public function swapFinishedAliases(): void
    {
        foreach ($this->getFinishedTargets() as $alias => $indices) {
            if (!$this->client->indices()->existsAlias(['name' => $alias])) {
                $this->promote($alias, $this->newest($indices), []);

                continue;
            }

            $live = array_keys($this->client->indices()->getAlias(['name' => $alias]));

            $finished = array_values(array_diff($indices, $live));

            if ($finished === []) {
                continue;
            }

            $this->promote($alias, $this->newest($finished), $live);
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

    /**
     * Two runs can finish before either was promoted. The rows are ordered by index name, whose suffix is the
     * creation timestamp, so the last one is the newest.
     *
     * @param non-empty-list<string> $indices
     */
    private function newest(array $indices): string
    {
        return $indices[array_key_last($indices)];
    }

    /**
     * @param array<string> $outdated indices the alias served so far
     */
    private function promote(string $alias, string $index, array $outdated): void
    {
        $this->putAlias($index, $alias);

        foreach ($outdated as $previous) {
            $this->client->indices()->delete(['index' => $previous]);
        }

        // drops the rows of the replaced indices and of any run that finished but lost the promotion, which leaves
        // those indices unused
        $this->connection->executeStatement(
            'DELETE FROM admin_elasticsearch_index_task WHERE `alias` = :alias AND `index` != :index',
            ['alias' => $alias, 'index' => $index]
        );
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
                ['entityName' => $indexer->getEntity(), 'parameters' => [], 'textBoosted' => '', 'text' => '', 'completion' => []],
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

        if (\is_array($result) && ((bool) ($result['errors'] ?? false)) !== false) {
            $errors = $this->parseErrors($result);

            throw ElasticsearchException::indexingError($errors);
        }

        if (!$message->isIndexingRun()) {
            return;
        }

        // counted down only after a successful write, so a failing batch keeps the alias on the previous index
        // instead of promoting an incomplete one
        $this->connection->executeStatement(
            'UPDATE admin_elasticsearch_index_task SET `doc_count` = `doc_count` - :count WHERE `index` = :index',
            ['count' => \count($ids), 'index' => $indices[$alias]]
        );
    }

    /**
     * @return array<string, non-empty-list<string>> indices to write to, keyed by alias
     */
    private function getWriteTargets(): array
    {
        return $this->groupByAlias(
            $this->connection->fetchAllAssociative('SELECT `alias`, `index` FROM admin_elasticsearch_index_task')
        );
    }

    /**
     * @return array<string, non-empty-list<string>> indices whose indexing run completed, keyed by alias
     */
    private function getFinishedTargets(): array
    {
        return $this->groupByAlias(
            $this->connection->fetchAllAssociative('SELECT `alias`, `index` FROM admin_elasticsearch_index_task WHERE `doc_count` <= 0 ORDER BY `index`')
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, non-empty-list<string>>
     */
    private function groupByAlias(array $rows): array
    {
        $targets = [];
        foreach ($rows as $row) {
            $targets[(string) $row['alias']][] = (string) $row['index'];
        }

        return $targets;
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
        $indices = [];
        foreach ($entities as $entityName) {
            $indexer = $this->getIndexer($entityName);
            $alias = $this->adminEsHelper->getIndex($indexer->getName());
            $index = $alias . '_' . $this->clock->now()->getTimestamp();

            if ($this->client->indices()->exists(['index' => $index])) {
                continue;
            }

            $indices[$alias] = $index;

            $this->create($indexer, $index, $alias);

            // rows of earlier runs that never finished are dropped, which leaves their indices unused. Finished
            // rows stay, so refresh() keeps writing to the index the alias serves until this run is promoted.
            $this->connection->executeStatement(
                'DELETE FROM admin_elasticsearch_index_task WHERE `entity` = :entity AND `index` != :index AND `doc_count` > 0',
                ['entity' => $indexer->getEntity(), 'index' => $index]
            );

            $iterator = $indexer->getIterator();

            $this->connection->insert('admin_elasticsearch_index_task', [
                'id' => Uuid::randomBytes(),
                '`entity`' => $indexer->getEntity(),
                '`index`' => $index,
                '`alias`' => $alias,
                '`doc_count`' => $iterator->fetchCount(),
            ]);
        }

        return $indices;
    }

    private function refreshIndices(): void
    {
        $indexTasks = [];
        $entities = [];
        foreach ($this->indexer as $indexer) {
            $alias = $this->adminEsHelper->getIndex($indexer->getName());

            if ($this->client->indices()->existsAlias(['name' => $alias])) {
                continue;
            }

            $index = $alias . '_' . $this->clock->now()->getTimestamp();
            $this->create($indexer, $index, $alias);

            $entities[] = $indexer->getEntity();

            // create() attached the alias right away because it did not exist, so this index is live from the start
            // and has no indexing run to wait for
            $indexTasks[] = [
                'id' => Uuid::randomBytes(),
                '`entity`' => $indexer->getEntity(),
                '`index`' => $index,
                '`alias`' => $alias,
                '`doc_count`' => 0,
            ];
        }

        if ($entities === []) {
            return;
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
     * @return list<array{index: string, id: string, type: string, reason: string}>
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
                'textBoosted' => AbstractAdminIndexer::TEXT_FIELD,
                'text' => AbstractAdminIndexer::TEXT_FIELD,
                'completion' => AbstractAdminIndexer::COMPLETION_FIELD,
                'entityName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'parameters' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ],
        ];

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
        if ($this->indexers !== null) {
            return $this->indexers;
        }

        $this->indexers = $this->indexer instanceof \Traversable
            ? iterator_to_array($this->indexer)
            : $this->indexer;

        return $this->indexers;
    }
}
