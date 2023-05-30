<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
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

    /**
     * @param IndexerOffset|null $offset
     */
    public function iterate($offset): ?ElasticsearchIndexingMessage
    {
        if (!$this->helper->allowIndexing()) {
            return null;
        }

        if ($offset === null) {
            $offset = $this->init();
        }

        if ($offset->getLanguageId() === null) {
            return null;
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
        $offset->selectNextLanguage();
        $offset->resetDefinitions();
        $offset->setLastId(null);

        return $this->iterate($offset);
    }

    /**
     * @param array<string> $ids
     */
    public function updateIds(EntityDefinition $definition, array $ids): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $alias = $this->helper->getIndexName($definition, Defaults::LANGUAGE_SYSTEM);

        if (!$this->client->indices()->existsAlias(['name' => $alias])) {
            $this->init();
        }

        $messages = $this->generateMessages($definition, $ids);

        /** @var ElasticsearchIndexingMessage $message */
        foreach ($messages as $message) {
            $this->__invoke($message);
        }
    }

    /**
     * @param array<string> $ids
     *
     * @return ElasticsearchIndexingMessage[]
     */
    private function generateMessages(EntityDefinition $definition, array $ids): array
    {
        $languages = $this->getLanguages();

        $messages = [];
        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

            $alias = $this->helper->getIndexName($definition, $language->getId());

            $indexing = new IndexingDto($ids, $alias, $definition->getEntityName());

            $message = new ElasticsearchIndexingMessage($indexing, null, $context);

            $messages[] = $message;
        }

        return $messages;
    }

    private function createIndexingMessage(IndexerOffset $offset, Context $context): ?ElasticsearchIndexingMessage
    {
        $definition = $this->registry->get((string) $offset->getDefinition());

        if (!$definition) {
            throw new \RuntimeException(sprintf('Definition %s not found', $offset->getDefinition()));
        }

        $entity = $definition->getEntityDefinition()->getEntityName();

        $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition(), $offset->getLastId(), $this->indexingBatchSize);

        $ids = $iterator->fetch();

        // current definition in current language has more ids to index
        if (!empty($ids)) {
            // increment last id with iterator offset
            $offset->setLastId($iterator->getOffset());

            $alias = $this->helper->getIndexName($definition->getEntityDefinition(), (string) $offset->getLanguageId());

            $index = $alias . '_' . $offset->getTimestamp();

            // return indexing message for current offset
            return new ElasticsearchIndexingMessage(new IndexingDto(array_values($ids), $index, $entity), $offset, $context);
        }

        if (!$offset->hasNextDefinition()) {
            return null;
        }

        // increment definition offset
        $offset->selectNextDefinition();

        // reset last id to start iterator at the beginning
        $offset->setLastId(null);

        return $this->createIndexingMessage($offset, $context);
    }

    private function init(): IndexerOffset
    {
        $this->connection->executeStatement('DELETE FROM elasticsearch_index_task');

        $this->createScripts();

        $languages = $this->getLanguages();

        $timestamp = new \DateTime();

        foreach ($languages as $language) {
            $this->createLanguageIndex($language, $timestamp);
        }

        return new IndexerOffset(
            array_values($languages->getIds()),
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

    private function getLanguages(): LanguageCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('salesChannels.id', null)]));
        $criteria->addSorting(new FieldSorting('id'));

        $this->eventDispatcher->dispatch(new ElasticsearchIndexerLanguageCriteriaEvent($criteria, $context));

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
            array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM])
        );
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

    private function createLanguageIndex(LanguageEntity $language, \DateTime $timestamp): void
    {
        $context = $this->createLanguageContext($language);

        foreach ($this->registry->getDefinitions() as $definition) {
            $alias = $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId());

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
            throw new \RuntimeException(sprintf('Entity %s has no registered elasticsearch definition', $entity));
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

            throw new ElasticsearchIndexingException($errors);
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

        $offset = new IndexerOffset([$language->getId()], $this->registry->getDefinitions(), $timestamp->getTimestamp());
        while ($message = $this->iterate($offset)) {
            $offset = $message->getOffset();

            $this->bus->dispatch($message);
        }
    }
}
