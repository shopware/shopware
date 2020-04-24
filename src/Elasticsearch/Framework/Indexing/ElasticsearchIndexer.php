<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer as AbstractEntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

class ElasticsearchIndexer extends AbstractEntityIndexer
{
    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    /**
     * @var ElasticsearchRegistry
     */
    private $registry;

    /**
     * @var IndexCreator
     */
    private $indexCreator;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $entityRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    public function __construct(
        EntityRepositoryInterface $languageRepository,
        Connection $connection,
        ElasticsearchHelper $helper,
        ElasticsearchRegistry $registry,
        IndexCreator $indexCreator,
        IteratorFactory $iteratorFactory,
        Client $client,
        DefinitionInstanceRegistry $entityRegistry,
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        CacheClearer $cacheClearer
    ) {
        $this->languageRepository = $languageRepository;
        $this->connection = $connection;
        $this->helper = $helper;
        $this->registry = $registry;
        $this->indexCreator = $indexCreator;
        $this->iteratorFactory = $iteratorFactory;
        $this->client = $client;
        $this->entityRegistry = $entityRegistry;
        $this->logger = $logger;
        $this->messageBus = $messageBus;
        $this->cacheClearer = $cacheClearer;
    }

    public function getName(): string
    {
        return 'elasticsearch.indexer';
    }

    /**
     * @param null|IndexerOffset $offset
     */
    public function iterate($offset): ?EntityIndexingMessage
    {
        if (!$this->helper->allowIndexing()) {
            return null;
        }

        if ($offset === null) {
            $offset = $this->init();
        }

        $language = $this->getLanguageForId($offset->getLanguageId());

        if (!$language) {
            return null;
        }

        $context = $this->createLanguageContext($language);

        // current language has next message?
        $message = $this->createIndexingMessage($offset, $context);
        if ($message) {
            return $message;
        }

        // all definitions in all languages indexed
        if (!$offset->hasNextLanguage()) {
            return null;
        }

        // all definitions are indexed in current language, start again with next language
        $offset->setNextLanguage();
        $offset->resetDefinitions();
        $offset->setLastId(null);

        return $this->iterate($offset);
    }

    /**
     * @deprecated tag:v6.3.0 - Each entity has to handle the update process by itself
     */
    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        if (!$this->helper->allowIndexing()) {
            return null;
        }

        /** @var EntityWrittenEvent $written */
        foreach ($event->getEvents() as $written) {
            $definition = $this->entityRegistry->getByEntityName($written->getEntityName());

            if (!$this->helper->isSupported($definition)) {
                continue;
            }

            $esDefinition = $this->registry->get($written->getEntityName());

            // @deprecated tag:v6.3.0 - Whole if condition will be removed, entities without elastic search definition are not supported
            if (!$esDefinition) {
                $this->sendIndexingMessages($definition, $written->getIds());

                continue;
            }

            // @deprecated tag:v6.3.0 - While if condition will be removed, each entity has to handle the update process by itself
            if ($esDefinition->hasNewIndexerPattern()) {
                continue;
            }

            $this->sendIndexingMessages($definition, $written->getIds());
        }

        return null;
    }

    public function sendIndexingMessages(EntityDefinition $definition, array $ids): void
    {
        $languages = $this->getLanguages();

        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

            $alias = $this->helper->getIndexName($definition, $language->getId());

            $indexing = new IndexingDto($ids, $alias, $definition->getEntityName());

            $message = new EntityIndexingMessage($indexing, null, $context);
            $message->setIndexer($this->getName());

            $this->messageBus->dispatch($message);
        }
    }

    public function handle(EntityIndexingMessage $message): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        /** @var IndexingDto $task */
        $task = $message->getData();

        $ids = $task->getIds();

        $index = $task->getIndex();

        if (!$this->client->indices()->exists(['index' => $index])) {
            return;
        }

        $entity = $task->getEntity();

        $definition = $this->registry->get($entity);

        $context = $message->getContext();

        if (!$definition) {
            throw new \RuntimeException(sprintf('Entity %s has no registered elasticsearch definition', $entity));
        }

        $repository = $this->entityRegistry->getRepository($entity);

        $criteria = new Criteria($ids);

        $definition->extendCriteria($criteria);

        /** @var EntitySearchResult $entities */
        $entities = $context->disableCache(function (Context $context) use ($repository, $criteria) {
            $context->setConsiderInheritance(true);

            return $repository->search($criteria, $context);
        });

        $toRemove = array_filter($ids, function (string $id) use ($entities) {
            return !$entities->has($id);
        });

        $entities = $definition->extendEntities($entities);

        $documents = $this->createDocuments($definition, $entities);

        $documents = $this->mapExtensionsToRoot($documents);

        foreach ($toRemove as $id) {
            $documents[] = ['delete' => ['_id' => $id]];
        }

        // index found entities
        $result = $this->client->bulk([
            'index' => $index,
            'type' => $definition->getEntityDefinition()->getEntityName(),
            'body' => $documents,
        ]);

        $this->cacheClearer->invalidateTags([$entity . '.id']);

        if (isset($result['errors']) && $result['errors']) {
            $errors = $this->parseErrors($result);

            throw new ElasticsearchIndexingException($errors);
        }
    }

    private function createIndexingMessage(IndexerOffset $offset, Context $context): ?EntityIndexingMessage
    {
        $definition = $this->registry->get($offset->getDefinition());

        if (!$definition) {
            throw new \RuntimeException(sprintf('Definition %s not found', $offset->getDefinition()));
        }

        $entity = $definition->getEntityDefinition()->getEntityName();

        $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition(), $offset->getLastId());

        $ids = $iterator->fetch();

        // current definition in current language has more ids to index
        if (!empty($ids)) {
            // increment last id with iterator offset
            $offset->setLastId($iterator->getOffset());

            $alias = $this->helper->getIndexName($definition->getEntityDefinition(), $offset->getLanguageId());

            $index = $alias . '_' . $offset->getTimestamp();

            // return indexing message for current offset
            return new ElasticsearchIndexingMessage(new IndexingDto(array_values($ids), $index, $entity), $offset, $context);
        }

        if (!$offset->hasNextDefinition()) {
            return null;
        }

        // increment definition offset
        $offset->setNextDefinition();

        // reset last id to start iterator at the beginning
        $offset->setLastId(null);

        return $this->createIndexingMessage($offset, $context);
    }

    private function init(): IndexerOffset
    {
        // reset all other indexing processes
        $this->clearIndexingTasks();

        $definitions = $this->registry->getDefinitions();
        $languages = $this->getLanguages();

        $timestamp = new \DateTime();

        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

            foreach ($definitions as $definition) {
                $alias = $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId());

                $index = $alias . '_' . $timestamp->getTimestamp();

                $this->indexCreator->createIndex($definition, $index, $context);

                $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition());

                $this->connection->insert('elasticsearch_index_task', [
                    'id' => Uuid::randomBytes(),
                    '`entity`' => $definition->getEntityDefinition()->getEntityName(),
                    '`index`' => $index,
                    '`alias`' => $alias,
                    '`doc_count`' => $iterator->fetchCount(),
                ]);
            }
        }

        return new IndexerOffset(
            $languages,
            $definitions,
            $timestamp->getTimestamp()
        );
    }

    private function mapExtensionsToRoot(array $documents): array
    {
        $extensions = [];

        foreach ($documents as $key => $document) {
            if ($key === 'extensions') {
                $extensions = $document;
                unset($documents['extensions']);

                continue;
            }

            if (is_array($document)) {
                $documents[$key] = $this->mapExtensionsToRoot($document);
            }
        }

        foreach ($extensions as $extensionKey => $extension) {
            if (is_array($extension)) {
                $documents[$extensionKey] = $this->mapExtensionsToRoot($extension);
            } else {
                $documents[$extensionKey] = $extension;
            }
        }

        return $documents;
    }

    private function createDocuments(AbstractElasticsearchDefinition $definition, iterable $entities): array
    {
        $documents = [];

        /** @var Entity $entity */
        foreach ($entities as $entity) {
            $documents[] = ['index' => ['_id' => $entity->getUniqueIdentifier()]];

            $document = json_decode(json_encode($entity, JSON_PRESERVE_ZERO_FRACTION), true);

            $fullText = $definition->buildFullText($entity);

            $document['fullText'] = $fullText->getFullText();
            $document['fullTextBoosted'] = $fullText->getBoosted();

            $documents[] = $document;
        }

        return $documents;
    }

    private function parseErrors(array $result): array
    {
        $errors = [];
        foreach ($result['items'] as $item) {
            $item = $item['index'];

            if (in_array($item['status'], [200, 201], true)) {
                continue;
            }

            $errors[] = [
                'index' => $item['_index'],
                'id' => $item['_id'],
                'type' => $item['error']['type'],
                'reason' => $item['error']['reason'],
            ];

            $this->logger->error($item['error']['reason']);
        }

        return $errors;
    }

    private function getLanguages(): LanguageCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('id'));

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository
            ->search($criteria, $context)
            ->getEntities();

        return $languages;
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

    private function clearIndexingTasks(): void
    {
        $this->connection->executeUpdate('DELETE FROM elasticsearch_index_task');
    }

    private function getLanguageForId(string $languageId): ?LanguageEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$languageId]);

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository
            ->search($criteria, $context);

        return $languages->get($languageId);
    }
}
