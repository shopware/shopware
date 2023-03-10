<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @final
 */
#[AsMessageHandler]
#[Package('core')]
class ElasticsearchIndexer
{
    /**
     * @deprecated tag:v6.6.0 - reason:blue-green-deployment - will be removed
     */
    public const ENABLE_MULTILINGUAL_INDEX_KEY = 'enable-multilingual-index';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ElasticsearchHelper $helper,
        private readonly ElasticsearchRegistry $registry,
        private readonly IndexCreator $indexCreator,
        private readonly IteratorFactory $iteratorFactory,
        private readonly Client $client,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $currencyRepository,
        private readonly EntityRepository $languageRepository,
        private readonly int $indexingBatchSize,
        private readonly MessageBusInterface $bus,
        private readonly MultilingualEsIndexer $newImplementation,
        private readonly ElasticsearchLanguageProvider $languageProvider,
        private readonly string $environment,
    ) {
    }

    public function __invoke(ElasticsearchIndexingMessage|ElasticsearchLanguageIndexIteratorMessage $message): void
    {
        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            if ($message instanceof ElasticsearchIndexingMessage) {
                $this->newImplementation->__invoke($message);
            }

            return;
        }

        if (!$this->helper->allowIndexing()) {
            return;
        }

        if ($message instanceof ElasticsearchLanguageIndexIteratorMessage) {
            $this->handleLanguageIndexIteratorMessage($message);

            return;
        }

        $this->handleIndexingMessage($message);
    }

    /**
     * @param IndexerOffset|null $offset
     * @param array<string> $entities
     */
    public function iterate($offset, array $entities = []): ?ElasticsearchIndexingMessage
    {
        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            return $this->newImplementation->iterate($offset);
        }

        if (!$this->helper->allowIndexing()) {
            return null;
        }

        if ($offset === null) {
            $offset = $this->init($entities);
        }

        return $this->createIndexingMessage($offset);
    }

    /**
     * @param array<string> $ids
     */
    public function updateIds(EntityDefinition $definition, array $ids): void
    {
        if ($this->helper->enabledMultilingualIndex()) {
            $this->newImplementation->updateIds($definition, $ids);

            return;
        }

        if (!$this->helper->allowIndexing()) {
            return;
        }

        $alias = $this->helper->getIndexName($definition);

        if (!$this->client->indices()->existsAlias(['name' => $alias])) {
            $this->init();
        }

        $this->__invoke($this->generateMessage($definition, $ids));
    }

    /**
     * @param array<string> $ids
     */
    private function generateMessage(EntityDefinition $definition, array $ids): ElasticsearchIndexingMessage
    {
        $context = Context::createDefaultContext();

        $alias = $this->helper->getIndexName($definition);

        $indexing = new IndexingDto($ids, $alias, $definition->getEntityName());

        return new ElasticsearchIndexingMessage($indexing, null, $context);
    }

    private function createIndexingMessage(IndexerOffset $offset): ?ElasticsearchIndexingMessage
    {
        $definition = $this->registry->get((string) $offset->getDefinition());

        if (!$definition) {
            throw ElasticsearchException::definitionNotFound((string) $offset->getDefinition());
        }

        $entity = $definition->getEntityDefinition()->getEntityName();

        $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition(), $offset->getLastId(), $this->indexingBatchSize);

        $ids = $iterator->fetch();

        if (!empty($ids)) {
            // increment last id with iterator offset
            $offset->setLastId($iterator->getOffset());

            $alias = $this->helper->getIndexName($definition->getEntityDefinition());

            $index = $alias . '_' . $offset->getTimestamp();

            // return indexing message for current offset
            return new ElasticsearchIndexingMessage(new IndexingDto(array_values($ids), $index, $entity), $offset, Context::createDefaultContext());
        }

        if (!$offset->hasNextDefinition()) {
            return null;
        }

        // increment definition offset
        $offset->selectNextDefinition();

        // reset last id to start iterator at the beginning
        $offset->setLastId(null);

        return $this->createIndexingMessage($offset);
    }

    /**
     * @param array<string> $entities
     */
    private function init(array $entities = []): IndexerOffset
    {
        $this->connection->executeStatement('DELETE FROM elasticsearch_index_task');

        $this->createScripts();

        $timestamp = new \DateTime();

        $this->createIndex($timestamp);

        $entitiesToHandle = $this->handleEntities($entities);

        return new IndexerOffset(
            $this->registry->getDefinitions(),
            $timestamp->getTimestamp()
        );
    }

    /**
     * @param array<string> $entities
     *
     * @return iterable<string>
     */
    private function handleEntities(array $entities = []): iterable
    {
        if (empty($entities)) {
            return $this->registry->getDefinitionNames();
        }

        $registeredEntities = \is_array($this->registry->getDefinitionNames())
            ? $this->registry->getDefinitionNames()
            : iterator_to_array($this->registry->getDefinitionNames());

        $validEntities = array_intersect($entities, $registeredEntities);
        $unregisteredEntities = array_diff($entities, $registeredEntities);

        if (!empty($unregisteredEntities)) {
            $unregisteredEntityList = implode(', ', $unregisteredEntities);

            if ($this->environment === 'prod') {
                $this->logger->error(sprintf('ElasticSearch indexing error. Entity definition(s) for %s not found.', $unregisteredEntityList));
            } else {
                throw ElasticsearchException::definitionNotFound($unregisteredEntityList);
            }
        }

        return $validEntities;
    }

    /**
     * @param array<mixed> $result
     *
     * @return array{index: string, id: string, type: string, reason: string}[]
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

            $this->logger->error($item['error']['reason'] ?? $item['result']);
        }

        return $errors;
    }

    private function getCurrencies(): EntitySearchResult
    {
        return $this->currencyRepository->search(new Criteria(), Context::createDefaultContext());
    }

    private function createScripts(): void
    {
        $finder = (new Finder())
            ->files()
            ->in(__DIR__ . '/Scripts')
            ->name('*.groovy');

        foreach ($finder as $file) {
            $name = pathinfo($file->getFilename(), \PATHINFO_FILENAME);

            $this->client->putScript([
                'id' => $name,
                'body' => [
                    'script' => [
                        'lang' => 'painless',
                        'source' => file_get_contents($file->getPathname()),
                    ],
                ],
            ]);
        }
    }

    private function createIndex(\DateTime $timestamp): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->registry->getDefinitions() as $definition) {
            $alias = $this->helper->getIndexName($definition->getEntityDefinition());

            $index = $alias . '_' . $timestamp->getTimestamp();

            $hasAlias = $this->indexCreator->aliasExists($alias);

            $this->indexCreator->createIndex($definition, $index, $alias, $context);

            $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition());

            // We don't need an index task, when it's the first indexing. This will allow alias swapping to nothing
            if ($hasAlias) {
                $this->connection->insert('elasticsearch_index_task', [
                    'id' => Uuid::randomBytes(),
                    '`entity`' => $definition->getEntityDefinition()->getEntityName(),
                    '`index`' => $index,
                    '`alias`' => $alias,
                    '`doc_count`' => $iterator->fetchCount(),
                ]);
            }
        }
    }

    private function handleIndexingMessage(ElasticsearchIndexingMessage $message): void
    {
        $task = $message->getData();

        $ids = $task->getIds();

        $index = $task->getIndex();

        $this->connection->executeStatement('UPDATE elasticsearch_index_task SET `doc_count` = `doc_count` - :idCount WHERE `index` = :index', [
            'idCount' => \count($ids),
            'index' => $index,
        ]);

        if (!$this->client->indices()->exists(['index' => $index])) {
            return;
        }

        $entity = $task->getEntity();

        $definition = $this->registry->get($entity);

        $context = $message->getContext();

        $context->addExtension('currencies', $this->currencyRepository->search(new Criteria(), Context::createDefaultContext()));

        if (!$definition) {
            throw ElasticsearchException::unsupportedElasticsearchDefinition($entity);
        }

        $languages = $this->connection->fetchAllKeyValue('SELECT DISTINCT LOWER(HEX(`language_id`)), LOWER(HEX(`parent_id`))  FROM sales_channel_language INNER JOIN language ON `sales_channel_language`.`language_id` = `language`.`id`');

        $data = $definition->fetch(Uuid::fromHexToBytesList($ids), $context, $languages);
        $toRemove = array_filter($ids, fn (string $id) => !isset($data[$id]));

        $documents = [];
        foreach ($data as $id => $document) {
            $documents[] = ['index' => ['_id' => $id]];
            $documents[] = $document;
        }

        foreach ($toRemove as $id) {
            $documents[] = ['delete' => ['_id' => $id]];
        }

        $arguments = [
            'index' => $index,
            'body' => $documents,
        ];

        $result = $this->client->bulk($arguments);

        if (\is_array($result) && isset($result['errors']) && $result['errors']) {
            $errors = $this->parseErrors($result);

            throw ElasticsearchException::indexingError($errors);
        }
    }

    private function handleLanguageIndexIteratorMessage(ElasticsearchLanguageIndexIteratorMessage $message): void
    {
        /** @var LanguageEntity|null $language */
        $language = $this->languageRepository->search(new Criteria([$message->getLanguageId()]), Context::createDefaultContext())->first();

        if ($language === null) {
            return;
        }

        $timestamp = new \DateTime();
        $this->createLanguageIndex($language, $timestamp);

        $offset = new IndexerOffset([$language->getId()], $this->registry->getDefinitionNames(), $timestamp->getTimestamp());
        while ($message = $this->iterate($offset)) {
            $offset = $message->getOffset();

            $this->bus->dispatch($message);
        }
    }}
