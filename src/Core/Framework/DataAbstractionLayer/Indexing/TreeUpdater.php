<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class TreeUpdater
{
    private ?Statement $updateEntityStatement = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param array<string> $updateIds
     */
    public function batchUpdate(array $updateIds, string $entity, Context $context): void
    {
        $updateIds = Uuid::fromHexToBytesList(array_unique($updateIds));
        if (empty($updateIds)) {
            return;
        }

        $bag = new TreeUpdaterBag();

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
        $this->loadAllParents($updateIds, $definition, $context, $bag);

        // 2. set path and level
        $this->updateLevelRecursively($updateIds, $definition, $context, $bag);
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
        $ids = [];
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

        return $query->executeQuery()->fetchAllAssociative();
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

        RetryableQuery::retryable($this->connection, function () use ($query): void {
            $query->executeStatement();
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
        } catch (InvalidUuidException | InvalidUuidLengthException) {
            $path[] = $parent[$field->getPathField()];
        }

        return $path;
    }

    private function loadParents(string $parentId, EntityDefinition $definition, string $versionId): array
    {
        $query = $this->getEntityByIdQuery($parentId, $definition);
        $this->makeQueryVersionAware($definition, $versionId, $query);

        $result = $query->executeQuery()->fetchAssociative();

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

    private function loadAllParents(array $ids, EntityDefinition $definition, Context $context, TreeUpdaterBag $bag): void
    {
        $levels = 100;

        $parentIds = $ids;
        do {
            $ids = $this->fetchByColumn($parentIds, $definition, 'id', $context, $bag);

            $parentIds = [];
            foreach ($ids as $id) {
                $parent = $bag->getEntity($id);
                if ($parent !== null && $parent['parent_id'] !== null) {
                    $parentIds[$parent['parent_id']] = $parent['parent_id'];
                }
            }

            --$levels;
        } while ($parentIds !== [] && $levels >= 0);

        if ($levels <= 0) {
            throw new \RuntimeException('Reached max depth, aborting');
        }
    }

    private function fetchByColumn(array $ids, EntityDefinition $definition, string $column, Context $context, TreeUpdaterBag $bag): array
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
        $query->setParameter('ids', $ids, ArrayParameterType::STRING);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        $fetchedIds = [];
        foreach ($query->executeQuery()->fetchAllAssociative() as $entity) {
            $bag->addEntity($entity['id'], $entity);
            $fetchedIds[$entity['id']] = $entity['id'];
        }

        return $fetchedIds;
    }

    /**
     * @param array<string> $updateIds
     */
    private function updateLevelRecursively(array $updateIds, EntityDefinition $definition, Context $context, TreeUpdaterBag $bag): void
    {
        if (empty($updateIds)) {
            return;
        }

        /** @var TreePathField $pathField */
        $pathField = $definition->getFields()->filterInstance(TreePathField::class)->first();

        /** @var TreeLevelField $levelField */
        $levelField = $definition->getFields()->filterInstance(TreeLevelField::class)->first();

        foreach ($updateIds as $updateId) {
            $entity = $this->updatePath($updateId, $bag);
            if ($entity !== null) {
                $this->updateEntity($entity, $definition, $pathField, $levelField, $context, $bag);
            }
        }

        $childIds = $this->fetchByColumn($updateIds, $definition, 'parent_id', $context, $bag);

        $this->updateLevelRecursively($childIds, $definition, $context, $bag);
    }

    private function updateEntity(array $entity, EntityDefinition $definition, ?TreePathField $pathField, ?TreeLevelField $levelField, Context $context, TreeUpdaterBag $bag): void
    {
        if ($pathField === null && $levelField) {
            throw new \RuntimeException('`TreePathField` or `TreeLevelField` required.');
        }

        if ($this->updateEntityStatement === null) {
            $tableName = EntityDefinitionQueryHelper::escape($definition->getEntityName());
            $sql = 'UPDATE ' . $tableName . ' SET ';

            $sets = [];
            if ($pathField !== null) {
                $sets[] = EntityDefinitionQueryHelper::escape($pathField->getStorageName()) . ' = :path';
            }

            if ($levelField !== null) {
                $sets[] = EntityDefinitionQueryHelper::escape($levelField->getStorageName()) . ' = :level';
            }

            $sql .= implode(',', $sets);
            $sql .= ' WHERE `id` = :id';

            if ($definition->getField('version_id')) {
                $sql .= ' AND `version_id` = :version';
            }

            $sql .= ';';

            $this->updateEntityStatement = $this->connection->prepare($sql);
        }

        if ($bag->alreadyUpdated($entity['id'])) {
            return;
        }

        $update = [
            'id' => $entity['id'],
        ];

        if ($definition->getField('version_id')) {
            $update['version'] = Uuid::fromHexToBytes($context->getVersionId());
        }

        if ($pathField !== null) {
            $update['path'] = $entity['path'];
        }
        if ($levelField !== null) {
            $update['level'] = $entity['level'];
        }

        $this->updateEntityStatement->executeStatement($update);

        $bag->addUpdated($entity['id']);
    }

    private function updatePath(string $id, TreeUpdaterBag $bag): ?array
    {
        $entity = $bag->getEntity($id);
        if ($entity === null) {
            return null;
        }

        if ($entity['parent_id'] === null) {
            // fix props
            $entity['path'] = null;
            $entity['level'] = 1;

            $bag->addEntity($id, $entity);

            return $entity;
        }

        // already computed
        if (\array_key_exists('path', $entity)) {
            $bag->addEntity($id, $entity);

            return $entity;
        }

        $parent = $this->updatePath($entity['parent_id'], $bag);

        $entity['path'] = '';
        if ($parent !== null) {
            $path = $parent['path'] ?? '';
            $path = array_filter(explode('|', (string) $path));
            $path[] = Uuid::fromBytesToHex($parent['id']);
            $entity['path'] = '|' . implode('|', $path) . '|';
        }

        $entity['level'] = ($parent['level'] ?? 0) + 1;

        $bag->addEntity($id, $entity);

        return $entity;
    }
}
