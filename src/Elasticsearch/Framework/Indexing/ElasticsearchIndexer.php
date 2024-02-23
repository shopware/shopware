<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
        private readonly int $indexingBatchSize
    ) {
    }

    public function __invoke(ElasticsearchIndexingMessage $message): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $this->handleIndexingMessage($message);
    }

    /**
     * @param array<string> $entities
     */
    public function iterate(?IndexerOffset $offset = null, array $entities = []): ?ElasticsearchIndexingMessage
    {
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

        if (empty($ids)) {
            if (!$offset->hasNextDefinition()) {
                return null;
            }
            // increment definition offset
            $offset->selectNextDefinition();

            // reset last id to start iterator at the beginning
            $offset->setLastId(null);

            return $this->createIndexingMessage($offset);
        }

        // increment last id with iterator offset
        $offset->setLastId($iterator->getOffset());

        $alias = $this->helper->getIndexName($definition->getEntityDefinition());

        $index = $alias . '_' . $offset->getTimestamp();

        // return indexing message for current offset
        return new ElasticsearchIndexingMessage(new IndexingDto(array_values($ids), $index, $entity), $offset, Context::createDefaultContext());
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
            $entitiesToHandle,
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

        foreach ($this->registry->getDefinitions() as $definition) {
            $alias = $this->helper->getIndexName($definition->getEntityDefinition());

            $index = $alias . '_' . $timestamp->getTimestamp();

            $hasAlias = $this->indexCreator->aliasExists($alias);

            $this->indexCreator->createIndex($definition, $index, $alias, $context);

            $iterator = $definition->getIterator();
            if (!$iterator) {
                $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition());
            }

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
            throw ElasticsearchException::definitionNotFound($entity);
        }

        $data = $definition->fetch(Uuid::fromHexToBytesList($ids), $context);

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

            $exception = ElasticsearchException::definitionNotFound($unregisteredEntityList);

            $this->helper->logAndThrowException($exception);
        }

        return $validEntities;
    }
}
