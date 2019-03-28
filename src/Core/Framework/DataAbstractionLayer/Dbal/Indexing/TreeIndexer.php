<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TreeIndexer implements IndexerInterface
{
    /**
     * @var DefinitionRegistry
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
     * @var TagAwareAdapter
     */
    private $cache;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        DefinitionRegistry $definitionRegistry,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityDefinition|string $definition */
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (!$definition::isTreeAware()) {
                continue;
            }

            $entityName = $definition::getEntityName();
            $iterator = $this->createIterator($entityName);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent('Start indexing tree path of ' . $entityName, $iterator->fetchCount())
            );

            while ($ids = $iterator->fetch()) {
                $ids = array_map(function ($id) {
                    return Uuid::fromBytesToHex($id);
                }, $ids);

                $this->updateIds($ids, $definition, $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent('Finished indexing tree path of ' . $entityName)
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var EntityWrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $nested->getDefinition();

            if ($definition::isTreeAware()) {
                $this->updateIds($nested->getIds(), $definition, $nested->getContext());
            }
        }
    }

    private function updateIds(array $ids, $definition, Context $context): void
    {
        foreach ($ids as $id) {
            $this->update($id, $definition, $context);
        }
    }

    private function update(string $parentId, $definition, Context $context): void
    {
        $parent = $this->loadParents(
            Uuid::fromStringToBytes($parentId),
            $definition,
            Uuid::fromStringToBytes($context->getVersionId()));

        if ($parent === null) {
            return;
        }

        $updatedIds = $this->updateRecursive($parent, $definition, $context);

        $tags = array_map(function ($id) use ($definition) {
            return $this->cacheKeyGenerator->getEntityTag($id, $definition);
        }, $updatedIds);

        $this->cache->invalidateTags($tags);
    }

    private function updateRecursive(
        array $entity,
        $definition,
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
        $definition,
        Context $context
    ): array {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition::getEntityName());
        $query->from($escaped);

        $query->select($this->getFieldsToSelect($definition));
        $query->andWhere('parent_id = :id');
        $query->setParameter('id', $parent['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromStringToBytes($context->getVersionId()), $query);

        return $query->execute()->fetchAll();
    }

    private function updateTree(array $entity, $definition, Context $context): string
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition::getEntityName());
        $query->update($escaped);

        /** @var TreePathField $pathField */
        foreach ($definition::getFields()->filterInstance(TreePathField::class) as $pathField) {
            $path = 'null';

            if (array_key_exists('parent', $entity)) {
                $path = '"|' . implode('|', $this->buildPathArray($entity['parent'], $pathField)) . '|"';
            }

            $query->set($pathField->getStorageName(), $path);
        }

        /** @var TreeLevelField $field */
        foreach ($definition::getFields()->filterInstance(TreeLevelField::class) as $field) {
            $level = 1;

            if (array_key_exists('parent', $entity)) {
                $level = $entity['parent']['parentCount'] + 1;
            }

            $query->set($field->getStorageName(), (string) $level);
        }

        $query->andWhere('id = :id');
        $query->setParameter('id', $entity['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromStringToBytes($context->getVersionId()), $query);

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

    private function loadParents(string $parentId, $definition, string $versionId): ?array
    {
        $query = $this->getEntityByIdQuery($parentId, $definition);
        $this->makeQueryVersionAware($definition, $versionId, $query);

        $result = $query->execute()->fetch();

        if ($result === false) {
            return null;
        }

        $result['parentCount'] = 1;

        if ($result['parent_id']) {
            if ($definition::isVersionAware()) {
                $versionId = $result['parent_version_id'];
            }
            $result['parent'] = $this->loadParents($result['parent_id'], $definition, $versionId);
            $result['parentCount'] = $result['parent']['parentCount'] + 1;
        }

        return $result;
    }

    private function getFieldsToSelect($definition): array
    {
        $fields = ['id', 'parent_id'];

        if ($definition::isVersionAware()) {
            $fields[] = 'version_id';
            $fields[] = 'parent_version_id';
        }

        $fields = $definition::getFields()
            ->filterInstance(TreePathField::class)
            ->reduce(function (array $fields, TreePathField $field) {
                if (!in_array($field->getPathField(), $fields, true)) {
                    $fields[] = $field->getPathField();
                }

                return $fields;
            }, $fields);

        return $fields;
    }

    private function createIterator(string $entityName): IterableQuery
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($entityName);

        $query->from($escaped);

        $query->setMaxResults(50);

        $query->select('id', 'id AS entityId');
        $query->andWhere('parent_id IS NULL');

        return new OffsetQuery($query);
    }

    private function makeQueryVersionAware($definition, string $versionId, QueryBuilder $query): void
    {
        if ($definition::isVersionAware()) {
            $query->andWhere('version_id = :versionId');
            $query->setParameter('versionId', $versionId);
        }
    }

    private function getEntityByIdQuery(string $parentId, $definition): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition::getEntityName());

        $query->from($escaped);

        $query->select($this->getFieldsToSelect($definition));
        $query->andWhere('id = :id');
        $query->setParameter('id', $parentId);

        return $query;
    }
}
