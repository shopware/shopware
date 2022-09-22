<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Elasticsearch\Client;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class AdminSearchRegistry extends AbstractMessageHandler implements EventSubscriberInterface
{
    public const CONFIG_KEY_STORE_ADMIN_ES_INDICES = 'core.store.adminESIndices';

    /**
     * @var array<string, mixed>
     */
    private array $indexer;

    private MessageBusInterface $queue;

    private EventDispatcherInterface $dispatcher;

    private Client $client;

    private SystemConfigService $configService;

    private bool $refreshIndices;

    /**
     * @var array<mixed>
     */
    private array $config;

    /**
     * @var array<mixed>
     */
    private array $mapping;

    /**
     * @param AbstractAdminIndexer[] $indexer
     * @param array<mixed> $config
     * @param array<mixed> $mapping
     */
    public function __construct(
        $indexer,
        MessageBusInterface $queue,
        EventDispatcherInterface $dispatcher,
        Client $client,
        SystemConfigService $configService,
        bool $refreshIndices,
        array $config,
        array $mapping
    ) {
        $this->indexer = $indexer instanceof \Traversable ? iterator_to_array($indexer) : $indexer;
        $this->queue = $queue;
        $this->dispatcher = $dispatcher;
        $this->client = $client;
        $this->configService = $configService;
        $this->refreshIndices = $refreshIndices;
        $this->mapping = $mapping;

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

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['refresh', -1000],
            ],
        ];
    }

    public static function getHandledMessages(): iterable
    {
        return [
            AdminSearchIndexingMessage::class,
        ];
    }

    public function iterate(): void
    {
        $indices = $this->createIndices();

        foreach ($this->indexer as $indexer) {
            $iterator = $indexer->getIterator();

            $this->dispatcher->dispatch(new ProgressStartedEvent($indexer->getName(), $iterator->fetchCount()));

            while ($ids = $iterator->fetch()) {
                $this->queue->dispatch(new AdminSearchIndexingMessage($indexer->getName(), $indices, $ids));

                $this->dispatcher->dispatch(new ProgressAdvancedEvent(\count($ids)));
            }

            $this->dispatcher->dispatch(new ProgressFinishedEvent($indexer->getName()));
        }

        $this->swapAlias($indices);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if (!$this->isIndexedEntityWritten($event)) {
            return;
        }

        if ($this->refreshIndices) {
            $this->refreshIndices();
        }

        $indices = $this->configService->get(self::CONFIG_KEY_STORE_ADMIN_ES_INDICES);

        if (!\is_array($indices)) {
            return;
        }

        foreach ($this->indexer as $indexer) {
            $ids = $event->getPrimaryKeys($indexer->getEntity());

            if (empty($ids)) {
                continue;
            }
            $documents = $indexer->fetch($ids);

            $this->push($indexer, $indices, $documents, $ids);
        }
    }

    public function handle($message): void
    {
        if (!$message instanceof AdminSearchIndexingMessage) {
            return;
        }

        $indexer = $this->getIndexer($message->getIndexer());

        $documents = $indexer->fetch($message->getIds());

        $this->push($indexer, $message->getIndices(), $documents, $message->getIds());
    }

    /**
     * @return AbstractAdminIndexer[]
     */
    public function getIndexers(): iterable
    {
        return $this->indexer;
    }

    public function getIndexer(string $name): AbstractAdminIndexer
    {
        $indexer = $this->indexer[$name] ?? $this->indexer[$this->buildName($name)];

        if ($indexer) {
            return $indexer;
        }

        throw new ElasticsearchIndexingException([\sprintf('Indexer for name %s not found', $name)]);
    }

    private function isIndexedEntityWritten(EntityWrittenContainerEvent $event): bool
    {
        foreach ($this->indexer as $indexer) {
            $ids = $event->getPrimaryKeys($indexer->getEntity());

            if (!empty($ids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $indices
     * @param array<string, array<string|int, string>> $data
     * @param array<string> $ids
     */
    private function push(AbstractAdminIndexer $indexer, array $indices, array $data, array $ids): void
    {
        $toRemove = array_filter($ids, static function (string $id) use ($data): bool {
            return !isset($data[$id]);
        });

        $documents = [];
        foreach ($data as $id => $document) {
            $documents[] = ['index' => ['_id' => $id]];

            $documents[] = \array_replace(
                ['entityName' => $indexer->getEntity(), 'parameters' => [], 'text' => ''],
                $document
            );
        }

        foreach ($toRemove as $id) {
            $documents[] = ['delete' => ['_id' => $id]];
        }

        $arguments = [
            'index' => $indices[$indexer->getIndex()],
            'body' => $documents,
        ];

        $result = $this->client->bulk($arguments);

        if (\is_array($result) && !empty($result['errors'])) {
            $errors = $this->parseErrors($result);

            throw new ElasticsearchIndexingException($errors);
        }
    }

    /**
     * @return array<string, string>
     */
    private function createIndices(): array
    {
        $newIndices = [];
        foreach ($this->indexer as $indexer) {
            $alias = (string) $indexer->getIndex();
            $index = $alias . '_' . time();

            if ($this->indexExists($index)) {
                continue;
            }

            $this->create($indexer, $index, $alias);

            $newIndices[$alias] = $index;
        }

        $indices = $this->configService->get(self::CONFIG_KEY_STORE_ADMIN_ES_INDICES);

        if (\is_array($indices)) {
            $newIndices = \array_replace($indices, $newIndices);
        }

        $this->configService->set(self::CONFIG_KEY_STORE_ADMIN_ES_INDICES, $newIndices);

        return $newIndices;
    }

    private function refreshIndices(): void
    {
        $newIndices = [];
        foreach ($this->indexer as $indexer) {
            $alias = (string) $indexer->getIndex();

            if ($this->aliasExists($alias)) {
                continue;
            }

            $index = $alias . '_' . time();
            $this->create($indexer, $index, $alias);

            $newIndices[$alias] = $index;
        }

        if (empty($newIndices)) {
            return;
        }

        $indices = $this->configService->get(self::CONFIG_KEY_STORE_ADMIN_ES_INDICES);

        if (\is_array($indices)) {
            $newIndices = \array_replace($indices, $newIndices);
        }

        $this->configService->set(self::CONFIG_KEY_STORE_ADMIN_ES_INDICES, $newIndices);
    }

    private function create(AbstractAdminIndexer $indexer, string $index, string $alias): void
    {
        $mapping = $indexer->mapping([
            'properties' => [
                'id' => ['type' => 'keyword'],
                'text' => ['type' => 'text'],
                'entityName' => ['type' => 'keyword'],
                'parameters' => ['type' => 'keyword'],
            ],
        ]);

        $mapping = array_merge_recursive($mapping, $this->mapping);

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

    private function indexExists(string $name): bool
    {
        return $this->client->indices()->exists(['index' => $name]);
    }

    private function aliasExists(string $alias): bool
    {
        return $this->client->indices()->existsAlias(['name' => $alias]);
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

    private function buildName(string $name): string
    {
        return str_replace(EnvironmentHelper::getVariable('SHOPWARE_ADMIN_ES_INDEX_PREFIX', 'sw-admin') . '-', '', $name);
    }

    private function createAliasIfNotExisting(string $index, string $alias): void
    {
        $exist = $this->client->indices()->existsAlias(['name' => $alias]);

        if ($exist) {
            return;
        }

        $this->putAlias($index, $alias);
    }

    /**
     * @param array<string, string> $indices
     */
    private function swapAlias($indices): void
    {
        foreach ($indices as $alias => $index) {
            $exist = $this->client->indices()->existsAlias(['name' => $alias]);

            if (!$exist) {
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
}
