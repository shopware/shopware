<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\DefinitionRegistry;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EntityIndexer implements IndexerInterface
{
    /**
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityMapper
     */
    private $entityMapper;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        bool $enabled,
        DefinitionRegistry $esRegistry,
        Client $client,
        EventDispatcherInterface $eventDispatcher,
        EntityMapper $entityMapper,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $languageRepository,
        MessageBusInterface $messageBus,
        Connection $connection
    ) {
        $this->registry = $esRegistry;
        $this->client = $client;
        $this->eventDispatcher = $eventDispatcher;
        $this->entityMapper = $entityMapper;
        $this->iteratorFactory = $iteratorFactory;
        $this->languageRepository = $languageRepository;
        $this->enabled = $enabled;
        $this->messageBus = $messageBus;
        $this->connection = $connection;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        if (!$this->enabled) {
            return;
        }
        $definitions = $this->registry->getDefinitions();

        $context = Context::createDefaultContext();

        // clear all pending indexing tasks
        $this->connection->executeUpdate('DELETE FROM elasticsearch_indexing');

        /** @var LanguageCollection $languages */
        $languages = $context->disableCache(
            function (Context $uncached) {
                return $this
                    ->languageRepository
                    ->search(new Criteria(), $uncached)
                    ->getEntities();
            }
        );

        foreach ($languages as $language) {
            $context = new Context(
                new Context\SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM]
            );

            /** @var string|EntityDefinition $definition */
            foreach ($definitions as $definition) {
                $alias = $this->registry->getIndex($definition, $language->getId());

                $index = $alias . '_' . $timestamp->getTimestamp();

                $this->createIndex($definition, $index, $context);

                $count = $this->indexDefinition($index, $definition, $context);

                $this->connection->insert('elasticsearch_indexing', [
                    'id' => Uuid::randomBytes(),
                    '`entity`' => $definition->getEntityName(),
                    '`index`' => $index,
                    '`alias`' => $alias,
                    '`doc_count`' => $count,
                ]);
            }
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }
    }

    /**
     * Only used for unit tests because the container parameter bag is frozen and can not be changed at runtime.
     * Therefore this function can be used to test different behaviours
     *
     * @internal
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    private function indexDefinition(string $index, EntityDefinition $definition, Context $context): int
    {
        $iterator = $this->iteratorFactory->createIterator($definition);

        $count = $iterator->fetchCount();

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent(sprintf('Start indexing elastic search for entity %s', $definition->getEntityName()), $count),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(count($ids)),
                ProgressAdvancedEvent::NAME
            );

            $this->messageBus->dispatch(
                new IndexingMessage($ids, $index, $context, $definition->getEntityName())
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent(sprintf('Finished indexing elastic search for entity %s', $definition->getEntityName())),
            ProgressFinishedEvent::NAME
        );

        return $count;
    }

    private function indexExists(string $index): bool
    {
        return $this->client->indices()->exists(['index' => $index]);
    }

    private function createIndex(EntityDefinition $definition, string $index, Context $context): void
    {
        if ($this->indexExists($index)) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $this->client->indices()->create([
            'index' => $index,
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 2,
                    'mapping.total_fields.limit' => 5000,
                    'mapping.nested_fields.limit' => 500,
                ],
            ],
        ]);

        $mapping = $this->entityMapper->generate($definition, $context);

        $this->client->indices()->putMapping([
            'index' => $index,
            'type' => $definition->getEntityName(),
            'body' => $mapping,
            'include_type_name' => true,
        ]);
    }
}
