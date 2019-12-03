<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;

class EntityIndexer implements IndexerInterface
{
    /**
     * @var ElasticsearchRegistry
     */
    private $registry;

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
     * @var IndexCreator
     */
    private $indexCreator;

    /**
     * @var IndexMessageDispatcher
     */
    private $indexMessageDispatcher;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        ElasticsearchRegistry $esRegistry,
        ElasticsearchHelper $helper,
        EntityRepositoryInterface $languageRepository,
        Connection $connection,
        IndexCreator $indexCreator,
        IndexMessageDispatcher $indexMessageDispatcher,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->registry = $esRegistry;
        $this->languageRepository = $languageRepository;
        $this->connection = $connection;
        $this->helper = $helper;
        $this->indexCreator = $indexCreator;
        $this->indexMessageDispatcher = $indexMessageDispatcher;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $definitions = $this->registry->getDefinitions();
        $this->clearIndexingTasks();

        /** @var LanguageCollection $languages */
        $languages = $this->getLanguages();

        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

            foreach ($definitions as $definition) {
                $alias = $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId());

                $index = $alias . '_' . $timestamp->getTimestamp();

                $this->indexCreator->createIndex($definition, $index, $context);

                $count = $this->indexMessageDispatcher->dispatchForAllEntities($index, $definition->getEntityDefinition(), $context);

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

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $this->index($timestamp);

        return null;
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $languages = $this->getLanguages();

        /** @var EntityWrittenEvent $written */
        foreach ($event->getEvents() as $written) {
            $definition = $this->definitionRegistry->getByEntityName($written->getEntityName());

            if (!$this->helper->isSupported($definition)) {
                continue;
            }

            /** @var LanguageEntity $language */
            foreach ($languages as $language) {
                $context = $this->createLanguageContext($language);

                $index = $this->helper->getIndexName($definition, $language->getId());

                $this->indexMessageDispatcher->dispatchForIds($written->getIds(), $index, $definition, $context);
            }
        }
    }

    public static function getName(): string
    {
        return 'Swag.EntityIndexer';
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

    private function clearIndexingTasks(): void
    {
        $this->connection->executeUpdate('DELETE FROM elasticsearch_index_task');
    }
}
