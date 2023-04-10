<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
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
use Shopware\Core\Framework\Util\ArrayNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $indexingBatchSize,
        private readonly MessageBusInterface $bus
    ) {
    }

    public function __invoke(ElasticsearchIndexingMessage|ElasticsearchLanguageIndexIteratorMessage $message): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        if ($message instanceof ElasticsearchLanguageIndexIteratorMessage) {
            $this->handleLanguageIndexIteratorMessage($message);

            return;
        }

        $this->handleIndexingMessage($message);
    }

    public function iterate(?IndexerOffset $offset = null, ?string $languageId = null): ?ElasticsearchIndexingMessage
    {
        if (!$this->helper->allowIndexing()) {
            return null;
        }

        if ($offset === null) {
            $offset = $this->init();
        }

        return $this->createIndexingMessage($offset, $languageId);
    }

    /**
     * @param array<string> $ids
     */
    public function updateIds(EntityDefinition $definition, array $ids, ?string $languageId = null): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $alias = $this->helper->getIndexName($definition);

        if (!$this->client->indices()->existsAlias(['name' => $alias])) {
            $this->init();
        }

        $this->__invoke($this->generateMessage($definition, $ids, $languageId));
    }

    /**
     * @param array<string> $ids
     */
    private function generateMessage(EntityDefinition $definition, array $ids, ?string $languageId = null): ElasticsearchIndexingMessage
    {
        $context = Context::createDefaultContext();

        $alias = $this->helper->getIndexName($definition);

        $indexing = new IndexingDto($ids, $alias, $definition->getEntityName());

        return new ElasticsearchIndexingMessage($indexing, null, $context, $languageId);
    }

    private function createIndexingMessage(IndexerOffset $offset, ?string $languageId = null): ?ElasticsearchIndexingMessage
    {
        $definition = $this->registry->get((string) $offset->getDefinition());

        if (!$definition) {
            throw new \RuntimeException(sprintf('Definition %s not found', $offset->getDefinition()));
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
            return new ElasticsearchIndexingMessage(new IndexingDto(array_values($ids), $index, $entity), $offset, Context::createDefaultContext(), $languageId);
        }

        if (!$offset->hasNextDefinition()) {
            return null;
        }

        // increment definition offset
        $offset->selectNextDefinition();

        // reset last id to start iterator at the beginning
        $offset->setLastId(null);

        return $this->createIndexingMessage($offset, $languageId);
    }

    private function init(): IndexerOffset
    {
        $this->connection->executeStatement('DELETE FROM elasticsearch_index_task');

        $this->createScripts();

        $timestamp = new \DateTime();

        $this->createIndex($timestamp);

        return new IndexerOffset(
            $this->registry->getDefinitions(),
            $timestamp->getTimestamp()
        );
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
        $languageIds = $this->getLanguages();

        foreach ($this->registry->getDefinitions() as $definition) {
            $alias = $this->helper->getIndexName($definition->getEntityDefinition());

            $index = $alias . '_' . $timestamp->getTimestamp();

            $hasAlias = $this->indexCreator->aliasExists($alias);

            $this->indexCreator->createIndex($definition, $index, $alias, $context);

            $this->updateLanguageMapping($index, $definition, $languageIds);

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

        if (!$definition) {
            throw new \RuntimeException(sprintf('Entity %s has no registered elasticsearch definition', $entity));
        }

        $data = $definition->fetch($ids, $context);

        // If not IndexingMessage's language is not given, fetch all languages
        $languageIds = $message->getLanguageId() ? [$message->getLanguageId()] : $this->getLanguages();

        $data = array_merge_recursive($data, $definition->fetchTranslatedFields($ids, $languageIds, $context));

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

            throw new ElasticsearchIndexingException($errors);
        }
    }

    private function updateLanguageMapping(string $indexName, AbstractElasticsearchDefinition $definition, array $languageIds): void
    {
        $shouldUpdateMapping = false;
        $languageFields = $definition->getLanguageMapping($languageIds, Context::createDefaultContext());

        $existingFields = $this->client->indices()->getFieldMapping([
            'fields' => implode(',', array_keys($languageFields)),
            'index' => $indexName,
        ]);

        if (empty(array_values($existingFields)[0]['mappings'])) {
            $shouldUpdateMapping = true;
        }

        $mappings = [];
        foreach (array_column(array_values($existingFields)[0]['mappings'], 'mapping') as $mapping) {
            $mappings[array_key_first($mapping)] = array_values($mapping)[0];
        }

        if (!empty(array_diff(ArrayNormalizer::flatten($mappings), ArrayNormalizer::flatten($languageFields)))) {
            $shouldUpdateMapping = true;
        }

        if (!$shouldUpdateMapping) {
            return;
        }

        $this->client->indices()->putMapping([
            'index' => $indexName,
            'body' => [
                'properties' => $definition->getLanguageMapping($languageIds, Context::createDefaultContext()),
            ],
        ]);
    }

    private function handleLanguageIndexIteratorMessage(ElasticsearchLanguageIndexIteratorMessage $message): void
    {
        foreach ($this->registry->getDefinitions() as $definition) {
            $index = $this->helper->getIndexName($definition->getEntityDefinition());

            $this->updateLanguageMapping($index, $definition, [$message->getLanguageId()]);
        }

        $timestamp = new \DateTime();
        $offset = new IndexerOffset($this->registry->getDefinitions(), $timestamp->getTimestamp());

        $message = $this->createIndexingMessage($offset, $message->getLanguageId());

        if ($message === null) {
            return;
        }

        $this->handleIndexingMessage($message);
    }

    private function getLanguages(): array
    {
        return $this->connection->fetchFirstColumn('SELECT DISTINCT LOWER(HEX(`language_id`)) FROM sales_channel_language');
    }
}
