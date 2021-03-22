<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;

class TreeUpdater
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var Connection
     */
    private $connection;

    private array $entities = [];

    private ?Statement $updateEntityStatement = null;

    private array $updated = [];

    public function __construct(DefinitionInstanceRegistry $registry, Connection $connection)
    {
        $this->registry = $registry;
        $this->connection = $connection;
    }

    public function batchUpdate(array $updateIds, string $entity, Context $context): void
    {
        $updateIds = Uuid::fromHexToBytesList(array_unique($updateIds));
        if (empty($updateIds)) {
            return;
        }

        $this->entities = [];
        $this->updated = [];
        $this->updateEntityStatement = null;

        $definition = $this->registry->getByEntityName($entity);

        // the batch update does not support versioning, so fallback to single updates
        if ($definition->isVersionAware() && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            foreach ($updateIds as $id) {
                $this->singleUpdate(Uuid::fromBytesToHex($id), $entity, $context);
            }

            return;
        }

        // 1. fetch parents until all ids have reached parent_id === null
        $this->loadAllParents($updateIds, $definition, $context);

        // 2. set path and level
        $this->updateLevelRecursively($updateIds, $definition, $context);
    }

    private function singleUpdate(string $parentId, string $entity, Context $context): array
    {
        $definition = $this->registry->getByEntityName($entity);

        $parent = $this->loadParents(
            Uuid::fromHexToBytes($parentId),
            $definition,
            Uuid::fromHexToBytes($context->getVersionId())
        );

        if ($parent === []) {
            return [];
        }

        return $this->updateRecursive($parent, $definition, $context);
    }

    private function updateRecursive(array $entity, EntityDefinition $definition, Context $context): array
    {
        $ids[] = $this->updateTree($entity, $definition, $context);
        foreach ($this->getChildren($entity, $definition, $context) as $child) {
            $child['parent'] = $entity;
            $child['parentCount'] = $entity['parentCount'] + 1;
            $ids = array_merge($ids, $this->updateRecursive($child, $definition, $context));
        }

        return $ids;
    }

    private function getChildren(array $parent, EntityDefinition $definition, Context $context): array
    {
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

            if (\array_key_exists('parent', $entity)) {
                $path = '"|' . implode('|', $this->buildPathArray($entity['parent'], $pathField)) . '|"';
            }

            $query->set($pathField->getStorageName(), $path);
        }

        /** @var TreeLevelField $field */
        foreach ($definition->getFields()->filterInstance(TreeLevelField::class) as $field) {
            $level = 1;

            if (\array_key_exists('parent', $entity)) {
                $level = $entity['parent']['parentCount'] + 1;
            }

            $query->set($field->getStorageName(), (string) $level);
        }

        $query->andWhere('id = :id');
        $query->setParameter('id', $entity['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        RetryableQuery::retryable(function () use ($query): void {
            $query->execute();
        });

        return Uuid::fromBytesToHex($entity['id']);
    }

    private function buildPathArray(array $parent, TreePathField $field): array
    {
        $path = [];

        if (\array_key_exists('parent', $parent)) {
            $path = $this->buildPathArray($parent['parent'], $field);
        }

        try {
            $path[] = Uuid::fromBytesToHex($parent[$field->getPathField()]);
        } catch (InvalidUuidException | InvalidUuidLengthException $e) {
            $path[] = $parent[$field->getPathField()];
        }

        return $path;
    }

    private function loadParents(string $parentId, EntityDefinition $definition, string $versionId): array
    {
        $query = $this->getEntityByIdQuery($parentId, $definition);
        $this->makeQueryVersionAware($definition, $versionId, $query);

        $result = $query->execute()->fetch();

        if ($result === false) {
            return [];
        }

        $result['parentCount'] = 1;

        if ($result['parent_id']) {
            if ($definition->isVersionAware()) {
                $versionId = $result['parent_version_id'];
            }
            $result['parent'] = $this->loadParents($result['parent_id'], $definition, $versionId);
            if (isset($result['parent']['parentCount'])) {
                $result['parentCount'] = $result['parent']['parentCount'] + 1;
            }
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
                if (!\in_array($field->getPathField(), $fields, true)) {
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

    private function loadAllParents(array $ids, EntityDefinition $definition, Context $context): void
    {
        $levels = 100;

        $parentIds = $ids;
        do {
            $ids = $this->fetchByColumn($parentIds, $definition, 'id', $context);

            $parentIds = [];
            foreach ($ids as $id) {
                $parent = $this->entities[$id];
                if ($parent['parent_id'] !== null) {
                    $parentIds[$parent['parent_id']] = $parent['parent_id'];
                }
            }

            --$levels;
        } while ($parentIds !== [] && $levels >= 0);

        if ($levels <= 0) {
            throw new \RuntimeException('Reached max depth, aborting');
        }
    }

    private function fetchByColumn(array $ids, EntityDefinition $definition, string $column, Context $context): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());
        $column = EntityDefinitionQueryHelper::escape($column);
        $query->from($escaped);
        $query->select('id', 'parent_id');
        $query->andWhere($column . ' IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        $fetchedIds = [];
        foreach ($query->execute()->fetchAll() as $entity) {
            $this->entities[$entity['id']] = $entity;
            $fetchedIds[$entity['id']] = $entity['id'];
        }

        return $fetchedIds;
    }

    private function updateLevelRecursively(array $updateIds, EntityDefinition $definition, Context $context): void
    {
        if (empty($updateIds)) {
            return;
        }

        /** @var TreePathField $pathField */
        $pathField = $definition->getFields()->filterInstance(TreePathField::class)->first();

        /** @var TreeLevelField $levelField */
        $levelField = $definition->getFields()->filterInstance(TreeLevelField::class)->first();

        foreach ($updateIds as $updateId) {
            $entity = $this->updatePath($updateId);
            if ($entity !== null) {
                $this->updateEntity($entity, $pathField, $levelField, $context);
            }
        }

        $childIds = $this->fetchByColumn($updateIds, $definition, 'parent_id', $context);
        $this->updateLevelRecursively($childIds, $definition, $context);
    }

    /**
     * @param array{'id': string, 'path': string|null, 'level': int} $entity
     */
    private function updateEntity(array $entity, ?TreePathField $pathField, ?TreeLevelField $levelField, Context $context): void
    {
        if ($pathField === null && $levelField) {
            throw new \RuntimeException('`TreePathField` or `TreeLevelField` required.');
        }

        if ($this->updateEntityStatement === null) {
            $sql = '
                UPDATE `category`
                SET ';

            $sets = [];
            if ($pathField !== null) {
                $sets[] = EntityDefinitionQueryHelper::escape($pathField->getStorageName()) . ' = :path';
            }

            if ($levelField !== null) {
                $sets[] = EntityDefinitionQueryHelper::escape($levelField->getStorageName()) . ' = :level';
            }

            $sql .= implode(',', $sets);
            $sql .= ' WHERE `id` = :id AND `version_id` = :version';

            $this->updateEntityStatement = $this->connection->prepare($sql);
        }

        if (!isset($this->updated[$entity['id']])) {
            $update = [
                'id' => $entity['id'],
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ];
            if ($pathField !== null) {
                $update['path'] = $entity['path'];
            }
            if ($levelField !== null) {
                $update['level'] = $entity['level'];
            }

            $this->updateEntityStatement->execute($update);
            $this->updated[$entity['id']] = true;
        }
    }

    /**
     * @return array{'id': string, 'parent_id': string, 'path': string|null, 'level': int}|null
     */
    private function updatePath(string $id): ?array
    {
        $entity = $this->entities[$id] ?? null;
        if ($entity === null) {
            return null;
        }

        if ($entity['parent_id'] === null) {
            // fix props
            $entity['path'] = null;
            $entity['level'] = 1;

            return $this->entities[$id] = $entity;
        }

        // already computed
        if (\array_key_exists('path', $this->entities)) {
            return $this->entities[$id] = $entity;
        }

        $parent = $this->updatePath($entity['parent_id']);

        $entity['path'] = '';
        if ($parent !== null) {
            $path = $parent['path'] ?? '';
            $path = array_filter(explode('|', $path));
            $path[] = Uuid::fromBytesToHex($parent['id']);
            $entity['path'] = '|' . implode('|', $path) . '|';
        }

        $entity['level'] = ($parent['level'] ?? 0) + 1;

        return $this->entities[$id] = $entity;
    }
}
