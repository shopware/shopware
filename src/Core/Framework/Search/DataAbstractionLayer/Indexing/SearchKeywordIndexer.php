<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IndexTableOperator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Search\Util\SearchAnalyzerRegistry;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchKeywordIndexer implements IndexerInterface
{
    public const DICTIONARY = 'search_dictionary';

    public const DOCUMENT_TABLE = 'search_document';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SearchAnalyzerRegistry
     */
    private $analyzerRegistry;

    /**
     * @var IndexTableOperator
     */
    private $indexTableOperator;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    /**
     * @var RepositoryInterface
     */
    private $catalogRepository;

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        Connection $connection,
        ContainerInterface $container,
        DefinitionRegistry $registry,
        EventDispatcherInterface $eventDispatcher,
        SearchAnalyzerRegistry $analyzerRegistry,
        IndexTableOperator $indexTableOperator,
        RepositoryInterface $languageRepository,
        RepositoryInterface $catalogRepository
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->analyzerRegistry = $analyzerRegistry;
        $this->indexTableOperator = $indexTableOperator;
        $this->languageRepository = $languageRepository;
        $this->catalogRepository = $catalogRepository;
        $this->container = $container;
        $this->registry = $registry;
    }

    public function index(\DateTime $timestamp): void
    {
        $this->indexTableOperator->createTable(self::DICTIONARY, $timestamp);
        $this->indexTableOperator->createTable(self::DOCUMENT_TABLE, $timestamp);

        $dictionary = $this->indexTableOperator->getIndexName(self::DICTIONARY, $timestamp);
        $document = $this->indexTableOperator->getIndexName(self::DOCUMENT_TABLE, $timestamp);

        $this->connection->executeUpdate('ALTER TABLE `' . $dictionary . '` ADD PRIMARY KEY `language_keyword` (`keyword`, `scope`, `language_id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $dictionary . '` ADD INDEX `keyword` (`keyword`, `language_id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $dictionary . '` ADD FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->connection->executeUpdate('ALTER TABLE `' . $dictionary . '` ADD INDEX `scope_language_id` (`scope`, `language_id`);');

        $this->connection->executeUpdate('ALTER TABLE `' . $document . '` ADD PRIMARY KEY (`id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $document . '` ADD UNIQUE  KEY (`language_id`, `keyword`, `entity`, `entity_id`, `ranking`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $document . '` ADD FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->connection->executeUpdate('ALTER TABLE `' . $document . '` ADD INDEX (`entity_id`)');

        $languages = $this->languageRepository->search(new Criteria(), Context::createDefaultContext());
        $catalogIds = $this->catalogRepository->searchIds(new Criteria(), Context::createDefaultContext());

        $sourceContext = new SourceContext();
        $sourceContext->setSalesChannelId(Defaults::SALES_CHANNEL);

        foreach ($languages as $language) {
            $context = new Context(
                $sourceContext,
                $catalogIds->getIds(),
                [],
                Defaults::CURRENCY,
                $language->getId(),
                $language->getParentId(),
                Defaults::LIVE_VERSION
            );

            $this->indexContext($context, $timestamp);
        }

        $this->connection->transactional(function () use ($dictionary, $document) {
            $this->connection->executeUpdate('DELETE FROM ' . self::DOCUMENT_TABLE);
            $this->connection->executeUpdate('DELETE FROM ' . self::DICTIONARY);

            $this->connection->executeUpdate('REPLACE INTO ' . self::DOCUMENT_TABLE . ' SELECT * FROM ' . $document);
            $this->connection->executeUpdate('REPLACE INTO ' . self::DICTIONARY . ' SELECT * FROM ' . $dictionary);

            $this->connection->executeUpdate('DROP TABLE ' . $dictionary);
            $this->connection->executeUpdate('DROP TABLE ' . $document);
        });
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $events = $event->getEvents();

        if (!$events) {
            return;
        }

        /** @var EntityWrittenEvent $nested */
        foreach ($events as $nested) {
            $definition = $nested->getDefinition();

            if (!$definition::useKeywordSearch()) {
                continue;
            }

            $this->indexEntities($definition, $nested->getContext(), $nested->getIds(), self::DICTIONARY, self::DOCUMENT_TABLE);
        }
    }

    public static function stringReverse($keyword)
    {
        $keyword = (string) $keyword;
        $peaces = preg_split('//u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
        $peaces = array_reverse($peaces);

        return implode('', $peaces);
    }

    private function indexContext(Context $context, \DateTime $timestamp): void
    {
        foreach ($this->registry->getElements() as $definition) {
            /** @var string|EntityDefinition $definition */
            if (!$definition::useKeywordSearch()) {
                continue;
            }

            $iterator = $this->createIterator($definition);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf('Start analyzing search keywords for entity %s in language %s', $definition::getEntityName(), $context->getLanguageId()),
                    $iterator->fetchCount()
                )
            );

            $table = $this->indexTableOperator->getIndexName(self::DICTIONARY, $timestamp);
            $documentTable = $this->indexTableOperator->getIndexName(self::DOCUMENT_TABLE, $timestamp);

            while ($ids = $iterator->fetch()) {
                $ids = array_map(function ($id) {
                    return Uuid::fromBytesToHex($id);
                }, $ids);

                $this->indexEntities($definition, $context, $ids, $table, $documentTable);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished analyzing search keywords for entity %s for language %s', $definition::getEntityName(), $context->getLanguageId()))
            );
        }
    }

    private function createIterator(string $definition): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();

        /** @var string|EntityDefinition $definition */
        $escaped = EntityDefinitionQueryHelper::escape($definition::getEntityName());

        $query->select([$escaped . '.auto_increment', $escaped . '.id']);
        $query->from($escaped);
        $query->andWhere($escaped . '.auto_increment > :lastId');
        $query->addOrderBy($escaped . '.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }

    private function indexEntities(string $definition, Context $context, array $ids, string $table, string $documentTable): void
    {
        /** @var EntityDefinition $definition */
        /** @var EntityRepository $repository */
        $repository = $this->container->get($definition::getEntityName() . '.repository');

        $entities = $repository->read(new ReadCriteria($ids), $context);

        $queue = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $languageId = $this->connection->quote(Uuid::fromStringToBytes($context->getLanguageId()));

        /** @var string|EntityDefinition $definition */
        $entityName = $this->connection->quote($definition::getEntityName());

        foreach ($entities as $entity) {
            $keywords = $this->analyzerRegistry->analyze($definition, $entity, $context);

            $entityId = $this->connection->quote(Uuid::fromStringToBytes($entity->getId()));

            $total = \array_sum($keywords);

            if (empty($keywords)) {
                continue;
            }

            //allow max ranking of 1000 per entity, this allows to compare entity searches and small documents with big documents
            $perPoint = 1000 / $total;

            foreach ($keywords as $keyword => $ranking) {
                $reversed = static::stringReverse($keyword);

                $ranking = $perPoint * $ranking;

                $keyword = $this->connection->quote($keyword);
                $reversed = $this->connection->quote($reversed);
                $ranking = $this->connection->quote($ranking);

                $queue->addInsert($table, [
                    'scope' => $entityName,
                    'language_id' => $languageId,
                    'keyword' => $keyword,
                    'reversed' => $reversed,
                ], null, true);

                $queue->addInsert($documentTable, [
                    'id' => $this->connection->quote(Uuid::uuid4()->getBytes()),
                    'entity' => $entityName,
                    'entity_id' => $entityId,
                    'language_id' => $languageId,
                    'keyword' => $keyword,
                    'ranking' => $ranking,
                ], null, true);
            }
        }

        $queue->execute();
    }
}
