<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Elasticsearch\Framework\Event\CreateIndexingCriteriaEvent;
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
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        DefinitionRegistry $esRegistry,
        DefinitionInstanceRegistry $definitionRegistry,
        Client $client,
        EventDispatcherInterface $eventDispatcher,
        EntityMapper $entityMapper,
        IteratorFactory $iteratorFactory
    ) {
        $this->registry = $esRegistry;
        $this->client = $client;
        $this->eventDispatcher = $eventDispatcher;
        $this->entityMapper = $entityMapper;
        $this->iteratorFactory = $iteratorFactory;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $definitions = $this->registry->getDefinitions();

        $context = Context::createDefaultContext();

        /** @var string|EntityDefinition $definition */
        foreach ($definitions as $definition) {
            $index = $this->registry->getIndex($definition, $context) . '_' . $timestamp->getTimestamp();

            $this->createIndex($definition, $index, $context);

            $this->indexDefinition($index, $definition, $context);

            $alias = $this->registry->getIndex($definition, $context);
            $this->createAlias($index, $alias);
        }
        $this->cleanup();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
    }

    private function cleanup(): void
    {
        $aliases = $this->client->indices()->getAliases();

        foreach ($aliases as $index => $config) {
            if (empty($config['aliases'])) {
                $this->client->indices()->delete(['index' => $index]);
            }
        }
    }

    private function indexDefinition(string $index, EntityDefinition $definition, Context $context): void
    {
        $iterator = $this->iteratorFactory->createIterator($definition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent(sprintf('Start indexing elastic search for entity %s', $definition->getEntityName()), $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(count($ids)),
                ProgressAdvancedEvent::NAME
            );
            $this->indexEntities($index, $ids, $definition, $context);
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent(sprintf('Finished indexing elastic search for entity %s', $definition->getEntityName())),
            ProgressFinishedEvent::NAME
        );
    }

    private function createAlias(string $index, string $alias): void
    {
        $exist = $this->client->indices()->existsAlias(['name' => $alias]);

        if ($exist) {
            $this->switchAlias($index, $alias);

            return;
        }

        $this->client->indices()->putAlias([
            'index' => $index,
            'name' => $alias,
        ]);
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

    private function switchAlias(string $index, string $entity): void
    {
        $actions = [
            ['add' => ['index' => $index, 'alias' => $entity]],
        ];

        $current = $this->client->indices()->getAlias(['name' => $entity]);
        $current = array_keys($current);

        foreach ($current as $value) {
            $actions[] = ['remove' => ['index' => $value, 'alias' => $entity]];
        }
        $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
    }

    private function indexEntities(string $index, array $ids, EntityDefinition $definition, Context $context): void
    {
        $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

        if (!$repository instanceof EntityRepository) {
            throw new \RuntimeException('Expected entity repository for service: ' . $definition->getEntityName() . '.repository');
        }

        $criteria = new Criteria($ids);

        $this->eventDispatcher->dispatch(
            new CreateIndexingCriteriaEvent($definition, $criteria, $context)
        );

        $entities = $context->disableCache(function (Context $context) use ($repository, $criteria) {
            $context->setConsiderInheritance(true);

            return $repository->search($criteria, $context);
        });

        /** @var EntitySearchResult $entities */
        if (empty($entities->getIds())) {
            return;
        }

        $documents = $this->createDocuments($entities);

        $this->client->bulk([
            'index' => $index,
            'type' => $definition->getEntityName(),
            'body' => $documents,
        ]);
    }

    private function deleteEntities(string $index, array $ids, EntityDefinition $definition): void
    {
        $deletes = array_map(function ($id) {
            return ['delete' => ['_id' => $id]];
        }, $ids);

        $this->client->bulk([
            'index' => $index,
            'type' => $definition->getEntityName(),
            'body' => $deletes,
        ]);
    }

    private function createDocuments(iterable $entities): array
    {
        $documents = [];

        /** @var Entity $entity */
        foreach ($entities as $entity) {
            $documents[] = ['index' => ['_id' => $entity->getUniqueIdentifier()]];
            $documents[] = json_decode(json_encode($entity, JSON_PRESERVE_ZERO_FRACTION), true);
        }

        return $documents;
    }
}
