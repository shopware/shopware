<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Language\LanguageEntity;
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

    /**
     * @var array
     */
    private $config;

    public function __construct(
        ElasticsearchRegistry $esRegistry,
        Client $client,
        ElasticsearchHelper $helper,
        EventDispatcherInterface $eventDispatcher,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $languageRepository,
        MessageBusInterface $messageBus,
        Connection $connection,
        array $config
    ) {
        $this->registry = $esRegistry;
        $this->client = $client;
        $this->eventDispatcher = $eventDispatcher;
        $this->iteratorFactory = $iteratorFactory;
        $this->languageRepository = $languageRepository;
        $this->messageBus = $messageBus;
        $this->connection = $connection;
        $this->helper = $helper;
        $this->config = $config;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $definitions = $this->registry->getDefinitions();

        // clear all pending indexing tasks
        $this->connection->executeUpdate('DELETE FROM elasticsearch_index_task');

        /** @var LanguageCollection $languages */
        $languages = $this->getLanguages();

        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

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
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $languages = $this->getLanguages();

        /** @var EntityWrittenEvent $written */
        foreach ($event->getEvents() as $written) {
            $definition = $written->getDefinition();

            if (!$this->helper->isSupported($definition)) {
                continue;
            }

            /** @var LanguageEntity $language */
            foreach ($languages as $language) {
                $context = $this->createLanguageContext($language);

                $index = $this->helper->getIndexName($definition, $language->getId());

                $this->messageBus->dispatch(
                    new IndexingMessage($written->getIds(), $index, $context, $definition->getEntityName())
                );
            }
        }
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
            'body' => $this->config,
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

    private function getLanguages(): EntityCollection
    {
        $context = Context::createDefaultContext();

        return $context->disableCache(
            function (Context $uncached) {
                return $this
                    ->languageRepository
                    ->search(new Criteria(), $uncached)
                    ->getEntities();
            }
        );
    }

    private function createLanguageContext(LanguageEntity $language): Context
    {
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM]
        );
    }
}
