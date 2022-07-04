<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Elasticsearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Elasticsearch\Admin\Indexer\AdminSearchIndexer;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdminSearchRegistry extends AbstractMessageHandler implements EventSubscriberInterface
{
    /**
     * @var AdminSearchIndexer[]
     */
    private iterable $indexer;

    private MessageBusInterface $queue;

    private EventDispatcherInterface $dispatcher;

    private Client $client;

    public function __construct(iterable $indexer, MessageBusInterface $queue, EventDispatcherInterface $dispatcher, Client $client)
    {
        $this->indexer = $indexer;
        $this->queue = $queue;
        $this->dispatcher = $dispatcher;
        $this->client = $client;
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
        $this->deleteIndices();

        $this->createIndices();

        foreach ($this->indexer as $indexer) {
            $iterator = $indexer->getIterator();

            $this->dispatcher->dispatch(new ProgressStartedEvent($indexer->getName(), $iterator->fetchCount()));

            while ($ids = $iterator->fetch()) {
                $this->queue->dispatch(new AdminSearchIndexingMessage($indexer->getName(), $ids));

                $this->dispatcher->dispatch(new ProgressAdvancedEvent(\count($ids)));
            }

            $this->dispatcher->dispatch(new ProgressFinishedEvent($indexer->getName()));
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $this->createIndices();

        foreach ($this->indexer as $indexer) {
            $ids = $event->getPrimaryKeys($indexer->getName());

            if (empty($ids)) {
                continue;
            }
            $documents = $indexer->fetch($ids);

            $this->push($indexer, $documents, $ids);
        }
    }

    public function handle($message): void
    {
        if (!$message instanceof AdminSearchIndexingMessage) {
            return;
        }

        $indexer = $this->getIndexer($message->getIndexer());

        $documents = $indexer->fetch($message->getIds());

        $this->push($indexer, $documents, $message->getIds());
    }

    /**
     * @return AdminSearchIndexer[]
     */
    public function getIndexers(): iterable
    {
        return $this->indexer;
    }

    public function getIndexer(string $name): AdminSearchIndexer
    {
        foreach ($this->indexer as $indexer) {
            if ($indexer->getName() === $name) {
                return $indexer;
            }
            if ($indexer->getIndex() === $name) {
                return $indexer;
            }
        }

        throw new \RuntimeException(\sprintf('Indexer for name %s not found', $name));
    }

    private function push(AdminSearchIndexer $indexer, array $data, array $ids): void
    {
        $toRemove = array_filter($ids, static fn (string $id) => !isset($data[$id]));

        $documents = [];
        foreach ($data as $id => $document) {
            $documents[] = ['index' => ['_id' => $id]];

            $documents[] = \array_replace(
                ['entity_name' => $indexer->getEntity(), 'parameters' => [], 'text' => ''],
                $document
            );
        }

        foreach ($toRemove as $id) {
            $documents[] = ['delete' => ['_id' => $id]];
        }

        $arguments = [
            'index' => $indexer->getIndex(),
            'body' => $documents,
        ];

        $result = $this->client->bulk($arguments);

        if (\is_array($result) && !empty($result['errors'])) {
            $errors = $this->parseErrors($result);

            throw new ElasticsearchIndexingException($errors);
        }
    }

    private function createIndices(): void
    {
        foreach ($this->indexer as $indexer) {
            if ($this->indexExists($indexer->getIndex())) {
                continue;
            }

            $mapping = $indexer->mapping([
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'text' => ['type' => 'text'],
                    'entity_name' => ['type' => 'keyword'],
                    'parameters' => ['type' => 'keyword'],
                ],
            ]);

            $this->client->indices()->create([
                'index' => $indexer->getIndex(),
                'body' => ['mappings' => $mapping],
            ]);
        }
    }

    private function indexExists(string $name): bool
    {
        return $this->client->indices()->exists(['index' => $name]);
    }

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

    private function deleteIndices(): void
    {
        foreach ($this->indexer as $indexer) {
            if (!$this->indexExists($indexer->getIndex())) {
                continue;
            }

            $this->client->indices()->delete(['index' => $indexer->getIndex()]);
        }
    }
}
