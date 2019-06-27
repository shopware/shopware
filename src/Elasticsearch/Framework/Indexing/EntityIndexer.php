<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EntityIndexer implements IndexerInterface
{
    /**
     * @var ElasticsearchRegistry
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

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    public function __construct(
        bool $enabled,
        ElasticsearchRegistry $esRegistry,
        Client $client,
        ElasticsearchHelper $helper,
        EventDispatcherInterface $eventDispatcher,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $languageRepository,
        MessageBusInterface $messageBus,
        Connection $connection
    ) {
        $this->registry = $esRegistry;
        $this->client = $client;
        $this->eventDispatcher = $eventDispatcher;
        $this->iteratorFactory = $iteratorFactory;
        $this->languageRepository = $languageRepository;
        $this->enabled = $enabled;
        $this->messageBus = $messageBus;
        $this->connection = $connection;
        $this->helper = $helper;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        if (!$this->enabled) {
            return;
        }

        $definitions = $this->registry->getDefinitions();

        $context = Context::createDefaultContext();

        // clear all pending indexing tasks
        $this->connection->executeUpdate('DELETE FROM elasticsearch_index_task');

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
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM]
            );

            foreach ($definitions as $definition) {
                $alias = $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId());

                $index = $alias . '_' . $timestamp->getTimestamp();

                $this->createIndex($definition, $index, $context);

                $count = $this->indexDefinition($index, $definition, $context);

                $this->connection->insert('elasticsearch_index_task', [
                    'id' => Uuid::randomBytes(),
                    '`entity`' => $definition->getEntityDefinition()->getEntityName(),
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

    private function indexDefinition(string $index, AbstractElasticsearchDefinition $definition, Context $context): int
    {
        $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition());

        $count = $iterator->fetchCount();

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent(sprintf('Start indexing elastic search for entity %s', $definition->getEntityDefinition()->getEntityName()), $count),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(count($ids)),
                ProgressAdvancedEvent::NAME
            );

            $this->messageBus->dispatch(
                new IndexingMessage($ids, $index, $context, $definition->getEntityDefinition()->getEntityName())
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent(sprintf('Finished indexing elastic search for entity %s', $definition->getEntityDefinition()->getEntityName())),
            ProgressFinishedEvent::NAME
        );

        return $count;
    }

    private function indexExists(string $index): bool
    {
        return $this->client->indices()->exists(['index' => $index]);
    }

    private function createIndex(AbstractElasticsearchDefinition $definition, string $index, Context $context): void
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

        $mapping = $definition->getMapping($context);

        $mapping = $this->addFullText($mapping);

        $this->client->indices()->putMapping([
            'index' => $index,
            'type' => $definition->getEntityDefinition()->getEntityName(),
            'body' => $mapping,
            'include_type_name' => true,
        ]);
    }

    private function addFullText(array $mapping): array
    {
        $mapping['properties']['fullText'] = ['type' => 'text'];
        $mapping['properties']['fullTextBoosted'] = ['type' => 'text'];

        if (!array_key_exists('_source', $mapping)) {
            return $mapping;
        }

        if (!array_key_exists('includes', $mapping['_source'])) {
            return $mapping;
        }

        $mapping['_source']['includes'][] = 'fullText';
        $mapping['_source']['includes'][] = 'fullTextBoosted';

        return $mapping;
    }
}
