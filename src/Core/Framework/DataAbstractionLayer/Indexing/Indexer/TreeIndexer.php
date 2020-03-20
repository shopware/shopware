<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater instead
 */
class TreeIndexer implements IndexerInterface
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        DefinitionInstanceRegistry $definitionRegistry,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache,
        IteratorFactory $iteratorFactory
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->iteratorFactory = $iteratorFactory;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (!$definition->isTreeAware()) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $iterator = $this->iteratorFactory->createIterator($definition);

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent('Start indexing tree path of ' . $entityName, $iterator->fetchCount()),
                ProgressStartedEvent::NAME
            );

            while ($ids = $iterator->fetch()) {
                $this->updateIds($ids, $definition, $context);

                $this->eventDispatcher->dispatch(
                    new ProgressAdvancedEvent(\count($ids)),
                    ProgressAdvancedEvent::NAME
                );
            }

            $this->eventDispatcher->dispatch(
                new ProgressFinishedEvent('Finished indexing tree path of ' . $entityName),
                ProgressFinishedEvent::NAME
            );
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $dataOffset = null;
        $definitionOffset = 0;
        if ($lastId) {
            $definitionOffset = $lastId['definitionOffset'];
            $dataOffset = $lastId['dataOffset'];
        }

        $definitions = array_values(array_filter(
            $this->definitionRegistry->getDefinitions(),
            function (EntityDefinition $definition) {
                return $definition->isTreeAware();
            }
        ));

        if (!isset($definitions[$definitionOffset])) {
            return null;
        }

        $definition = $definitions[$definitionOffset];

        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($definition, $dataOffset);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            ++$definitionOffset;

            return [
                'definitionOffset' => $definitionOffset,
                'dataOffset' => null,
            ];
        }

        $this->updateIds($ids, $definition, $context);

        return [
            'definitionOffset' => $definitionOffset,
            'dataOffset' => $iterator->getOffset(),
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var EntityWrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $this->definitionRegistry->getByEntityName($nested->getEntityName());

            if ($definition->isTreeAware()) {
                $this->updateIds($nested->getIds(), $definition, $nested->getContext());
            }
        }
    }

    public static function getName(): string
    {
        return 'Swag.TreeIndexer';
    }

    private function updateIds(array $ids, EntityDefinition $definition, Context $context): void
    {
        foreach ($ids as $id) {
            $this->update($id, $definition, $context);
        }
    }

    private function update(string $parentId, EntityDefinition $definition, Context $context): void
    {
        $parent = $this->loadParents(
            Uuid::fromHexToBytes($parentId),
            $definition,
            Uuid::fromHexToBytes($context->getVersionId())
        );

        if ($parent === null) {
            return;
        }

        $updatedIds = $this->updateRecursive($parent, $definition, $context);

        $tags = array_map(function ($id) use ($definition) {
            return $this->cacheKeyGenerator->getEntityTag($id, $definition->getEntityName());
        }, $updatedIds);

        $this->cache->invalidateTags($tags);
    }

    private function updateRecursive(
        array $entity,
        EntityDefinition $definition,
        Context $context
    ): array {
        $ids[] = $this->updateTree($entity, $definition, $context);
        foreach ($this->getChildren($entity, $definition, $context) as $child) {
            $child['parent'] = $entity;
            $child['parentCount'] = $entity['parentCount'] + 1;
            $ids = array_merge($ids, $this->updateRecursive($child, $definition, $context));
        }

        return $ids;
    }

    private function getChildren(
        array $parent,
        EntityDefinition $definition,
        Context $context
    ): array {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());
        $query->from($escaped);

        $query->select($this->getFieldsToSelect($definition));
        $query->andWhere('parent_id = :id');
        $query->setParameter('id', $parent['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        return $query->execute()->fetchAll();
    }

    private function updateTree(array $entity, EntityDefinition $definition, Context $context): string
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());
        $query->update($escaped);

        /** @var TreePathField $pathField */
        foreach ($definition->getFields()->filterInstance(TreePathField::class) as $pathField) {
            $path = 'null';

            if (array_key_exists('parent', $entity)) {
                $path = '"|' . implode('|', $this->buildPathArray($entity['parent'], $pathField)) . '|"';
            }

            $query->set($pathField->getStorageName(), $path);
        }

        /** @var TreeLevelField $field */
        foreach ($definition->getFields()->filterInstance(TreeLevelField::class) as $field) {
            $level = 1;

            if (array_key_exists('parent', $entity)) {
                $level = $entity['parent']['parentCount'] + 1;
            }

            $query->set($field->getStorageName(), (string) $level);
        }

        $query->andWhere('id = :id');
        $query->setParameter('id', $entity['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        $query->execute();

        return Uuid::fromBytesToHex($entity['id']);
    }

    private function buildPathArray(array $parent, TreePathField $field): array
    {
        $path = [];

        if (array_key_exists('parent', $parent)) {
            $path = $this->buildPathArray($parent['parent'], $field);
        }

        try {
            $path[] = Uuid::fromBytesToHex($parent[$field->getPathField()]);
        } catch (InvalidUuidException | InvalidUuidLengthException $e) {
            $path[] = $parent[$field->getPathField()];
        }

        return $path;
    }

    private function loadParents(string $parentId, EntityDefinition $definition, string $versionId): ?array
    {
        $query = $this->getEntityByIdQuery($parentId, $definition);
        $this->makeQueryVersionAware($definition, $versionId, $query);

        $result = $query->execute()->fetch();

        if ($result === false) {
            return null;
        }

        $result['parentCount'] = 1;

        if ($result['parent_id']) {
            if ($definition->isVersionAware()) {
                $versionId = $result['parent_version_id'];
            }
            $result['parent'] = $this->loadParents($result['parent_id'], $definition, $versionId);
            $result['parentCount'] = $result['parent']['parentCount'] + 1;
        }

        return $result;
    }

    private function getFieldsToSelect(EntityDefinition $definition): array
    {
        $fields = ['id', 'parent_id'];

        if ($definition->isVersionAware()) {
            $fields[] = 'version_id';
            $fields[] = 'parent_version_id';
        }

        $fields = $definition->getFields()
            ->filterInstance(TreePathField::class)
            ->reduce(function (array $fields, TreePathField $field) {
                if (!in_array($field->getPathField(), $fields, true)) {
                    $fields[] = $field->getPathField();
                }

                return $fields;
            }, $fields);

        return $fields;
    }

    private function makeQueryVersionAware(EntityDefinition $definition, string $versionId, QueryBuilder $query): void
    {
        if ($definition->isVersionAware()) {
            $query->andWhere('version_id = :versionId');
            $query->setParameter('versionId', $versionId);
        }
    }

    private function getEntityByIdQuery(string $parentId, EntityDefinition $definition): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());

        $query->from($escaped);

        $query->select($this->getFieldsToSelect($definition));
        $query->andWhere('id = :id');
        $query->setParameter('id', $parentId);

        return $query;
    }
}
