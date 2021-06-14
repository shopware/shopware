<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilderAlias;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldForeignKeyConstraintMissingException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\PrimaryKeyBag;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EntityWriteGateway implements EntityWriteGatewayInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ExceptionHandlerRegistry
     */
    private $exceptionHandlerRegistry;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private ?PrimaryKeyBag $primaryKeyBag = null;

    private int $batchSize;

    public function __construct(
        int $batchSize,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        ExceptionHandlerRegistry $exceptionHandlerRegistry,
        DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->exceptionHandlerRegistry = $exceptionHandlerRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->batchSize = $batchSize;
    }

    public function prefetchExistences(WriteParameterBag $parameters): void
    {
        $primaryKeyBag = $this->primaryKeyBag = $parameters->getPrimaryKeyBag();

        if ($primaryKeyBag->isPrefetchingCompleted()) {
            return;
        }

        foreach ($primaryKeyBag->getPrimaryKeys() as $entity => $pks) {
            $this->prefetch($this->definitionInstanceRegistry->getByEntityName($entity), $pks, $parameters);
        }

        $primaryKeyBag->setPrefetchingCompleted(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getExistence(EntityDefinition $definition, array $primaryKey, array $data, WriteCommandQueue $commandQueue): EntityExistence
    {
        $state = $this->getCurrentState($definition, $primaryKey, $commandQueue);

        $exists = !empty($state);

        $isChild = $this->isChild($definition, $data, $state, $primaryKey, $commandQueue);

        $wasChild = $this->wasChild($definition, $state);

        return new EntityExistence($definition->getEntityName(), Uuid::fromBytesToHexList($primaryKey), $exists, $isChild, $wasChild, $state);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $commands, WriteContext $context): void
    {
        try {
            $this->connection->beginTransaction();

            try {
                $this->executeCommands($commands, $context, true);
            } catch (\Throwable $e) {
                // retry with batch disabled
                $this->connection->rollBack();
                $this->connection->beginTransaction();

                $context->resetExceptions();
                $this->executeCommands($commands, $context, false);
            }

            //only commit if transaction is not already marked for rollback
            if (!$this->connection->isRollbackOnly()) {
                $this->connection->commit();
            } else {
                $this->connection->rollBack();
            }
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    private function executeCommands(array $commands, WriteContext $context, bool $enableBatch): void
    {
        // throws exception on violation and then aborts/rollbacks this transaction
        $event = new PreWriteValidationEvent($context, $commands);
        $this->eventDispatcher->dispatch($event);

        $this->generateChangeSets($commands);

        $context->getExceptions()->tryToThrow();

        $previous = null;
        $mappings = new MultiInsertQueryQueue($this->connection, $this->batchSize, false, true);
        $inserts = new MultiInsertQueryQueue($this->connection, $this->batchSize);

        foreach ($commands as $command) {
            if (!$command->isValid()) {
                continue;
            }
            $current = $command->getDefinition()->getEntityName();

            if ($current !== $previous) {
                $mappings->execute();
                $inserts->execute();
            }
            $previous = $current;

            try {
                $definition = $command->getDefinition();
                $table = $definition->getEntityName();

                if ($command instanceof DeleteCommand) {
                    $mappings->execute();
                    $inserts->execute();

                    RetryableQuery::retryable(function () use ($command, $table): void {
                        $this->connection->delete(
                            EntityDefinitionQueryHelper::escape($table),
                            $command->getPrimaryKey()
                        );
                    });

                    continue;
                }

                if ($command instanceof JsonUpdateCommand) {
                    $mappings->execute();
                    $inserts->execute();

                    $this->executeJsonUpdate($command);

                    continue;
                }

                if ($definition instanceof MappingEntityDefinition && $command instanceof InsertCommand) {
                    $mappings->addInsert($definition->getEntityName(), $command->getPayload());

                    if (!$enableBatch) {
                        $mappings->execute();
                    }

                    continue;
                }

                if ($command instanceof UpdateCommand) {
                    $mappings->execute();
                    $inserts->execute();

                    RetryableQuery::retryable(function () use ($command, $table): void {
                        $this->connection->update(
                            EntityDefinitionQueryHelper::escape($table),
                            $this->escapeColumnKeys($command->getPayload()),
                            $command->getPrimaryKey()
                        );
                    });

                    continue;
                }

                if ($command instanceof InsertCommand) {
                    $inserts->addInsert($definition->getEntityName(), $command->getPayload());

                    if (!$enableBatch) {
                        $inserts->execute();
                    }

                    continue;
                }

                throw new UnsupportedCommandTypeException($command);
            } catch (\Exception $e) {
                $innerException = $this->exceptionHandlerRegistry->matchException($e, $command);
                if ($innerException instanceof \Exception) {
                    $e = $innerException;
                }
                $context->getExceptions()->add($e);

                throw $e;
            }
        }

        $mappings->execute();
        $inserts->execute();

        // throws exception on violation and then aborts/rollbacks this transaction
        $event = new PostWriteValidationEvent($context, $commands);
        $this->eventDispatcher->dispatch($event);
        $context->getExceptions()->tryToThrow();
    }

    private function prefetch(EntityDefinition $definition, array $pks, WriteParameterBag $parameters): void
    {
        $pkFields = [];
        $versionField = null;
        /** @var StorageAware&Field $field */
        foreach ($definition->getPrimaryKeys() as $field) {
            if ($field instanceof VersionField) {
                $versionField = $field;

                continue;
            }
            if ($field instanceof StorageAware) {
                $pkFields[$field->getStorageName()] = $field;
            }
        }

        $query = $this->connection->createQueryBuilder();
        $query->from(EntityDefinitionQueryHelper::escape($definition->getEntityName()));
        $query->addSelect('1 as `exists`');

        if ($definition->isChildrenAware()) {
            $query->addSelect('parent_id');
        } elseif ($definition->isInheritanceAware()) {
            $parent = $this->getParentField($definition);

            if ($parent !== null) {
                $query->addSelect(
                    EntityDefinitionQueryHelper::escape($parent->getStorageName())
                    . ' as `parent`'
                );
            }
        }

        foreach ($pkFields as $storageName => $_) {
            $query->addSelect(EntityDefinitionQueryHelper::escape($storageName));
        }
        if ($versionField) {
            $query->addSelect(EntityDefinitionQueryHelper::escape($versionField->getStorageName()));
        }

        $chunks = array_chunk($pks, 500, true);

        foreach ($chunks as $pks) {
            $query->resetQueryPart('where');

            $params = [];
            $tupleCount = 0;

            foreach ($pks as $pk) {
                $newIds = [];
                /** @var Field&StorageAware $field */
                foreach ($pkFields as $field) {
                    $id = $pk[$field->getPropertyName()] ?? null;
                    if ($id === null) {
                        continue 2;
                    }
                    $newIds[] = Uuid::fromHexToBytes($id);
                }

                foreach ($newIds as $newId) {
                    $params[] = $newId;
                }

                ++$tupleCount;
            }

            if ($tupleCount <= 0) {
                continue;
            }

            $placeholders = $this->getPlaceholders(\count($pkFields), $tupleCount);
            $columns = '`' . implode('`,`', array_keys($pkFields)) . '`';
            if (\count($pkFields) > 1) {
                $columns = '(' . $columns . ')';
            }

            $query->andWhere($columns . ' IN (' . $placeholders . ')');
            if ($versionField) {
                $query->andWhere('version_id = ?');
                $params[] = Uuid::fromHexToBytes($parameters->getContext()->getContext()->getVersionId());
            }

            $query->setParameters($params);

            $result = $query->execute()->fetchAllAssociative();

            $primaryKeyBag = $parameters->getPrimaryKeyBag();

            foreach ($result as $state) {
                $values = [];
                foreach ($pkFields as $storageKey => $field) {
                    $values[$field->getPropertyName()] = Uuid::fromBytesToHex($state[$storageKey]);
                }
                if ($versionField) {
                    $values[$versionField->getPropertyName()] = $parameters->getContext()->getContext()->getVersionId();
                }

                $primaryKeyBag->addExistenceState($definition, $values, $state);
            }

            foreach ($pks as $pk) {
                if (!$primaryKeyBag->hasExistence($definition, $pk)) {
                    $primaryKeyBag->addExistenceState($definition, $pk, []);
                }
            }
        }
    }

    private static function isAssociative(array $array): bool
    {
        foreach ($array as $key => $_value) {
            if (!\is_int($key)) {
                return true;
            }
        }

        return false;
    }

    private function executeJsonUpdate(JsonUpdateCommand $command): void
    {
        /*
         * mysql json functions are tricky.
         *
         * TL;DR: cast objects and arrays to json
         *
         * This works:
         *
         * SELECT JSON_SET('{"a": "b"}', '$.a', 7)
         * SELECT JSON_SET('{"a": "b"}', '$.a', "str")
         *
         * This does NOT work:
         *
         * SELECT JSON_SET('{"a": "b"}', '$.a', '{"foo": "bar"}')
         *
         * Instead, you have to do this, because mysql cannot differentiate between a string and a json string:
         *
         * SELECT JSON_SET('{"a": "b"}', '$.a', CAST('{"foo": "bar"}' AS json))
         * SELECT JSON_SET('{"a": "b"}', '$.a', CAST('["foo", "bar"]' AS json))
         *
         * Yet this does NOT work:
         *
         * SELECT JSON_SET('{"a": "b"}', '$.a', CAST("str" AS json))
         *
         */

        $values = [];
        $sets = [];

        $query = new QueryBuilder($this->connection);
        $query->update('`' . $command->getDefinition()->getEntityName() . '`');

        foreach ($command->getPayload() as $attribute => $value) {
            // add path and value for each attribute value pair
            $values[] = '$."' . $attribute . '"';
            if (\is_array($value) || \is_object($value)) {
                $values[] = json_encode($value, \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE);
                // does the same thing as CAST(?, json) but works on mariadb
                $identityValue = \is_object($value) || self::isAssociative($value) ? '{}' : '[]';
                $sets[] = '?, JSON_MERGE("' . $identityValue . '", ?)';
            } else {
                $values[] = $value;
                $sets[] = '?, ?';
            }
        }

        $storageName = $command->getStorageName();
        $query->set(
            $storageName,
            sprintf(
                'JSON_SET(IFNULL(%s, "{}"), %s)',
                EntityDefinitionQueryHelper::escape($storageName),
                implode(', ', $sets)
            )
        );

        $identifier = $command->getPrimaryKey();
        foreach ($identifier as $key => $_value) {
            $query->andWhere(EntityDefinitionQueryHelper::escape($key) . ' = ?');
        }
        $query->setParameters(array_merge($values, array_values($identifier)));

        RetryableQuery::retryable(function () use ($query): void {
            $query->execute();
        });
    }

    private function escapeColumnKeys(array $payload): array
    {
        $escaped = [];
        foreach ($payload as $key => $value) {
            $escaped[EntityDefinitionQueryHelper::escape($key)] = $value;
        }

        return $escaped;
    }

    private function generateChangeSets(array $commands): void
    {
        $primaryKeys = [];
        $definitions = [];

        foreach ($commands as $command) {
            if (!$command instanceof ChangeSetAware || !$command instanceof WriteCommand) {
                continue;
            }

            if (!$command->requiresChangeSet()) {
                continue;
            }

            $entity = $command->getDefinition()->getEntityName();

            $primaryKeys[$entity][] = $command->getPrimaryKey();
            $definitions[$entity] = $command->getDefinition();
        }

        if (empty($primaryKeys)) {
            return;
        }

        $states = [];
        foreach ($primaryKeys as $entity => $ids) {
            $query = $this->connection->createQueryBuilder();

            $definition = $definitions[$entity];

            $query->addSelect('*');
            $query->from(EntityDefinitionQueryHelper::escape($definition->getEntityName()));

            $this->addPrimaryCondition($query, $ids);

            $states[$entity] = $query->execute()->fetchAll();
        }

        foreach ($commands as $command) {
            if (!$command instanceof ChangeSetAware || !$command instanceof WriteCommand) {
                continue;
            }

            if (!$command->requiresChangeSet()) {
                continue;
            }

            $entity = $command->getDefinition()->getEntityName();

            $command->setChangeSet(
                $this->calculateChangeSet($command, $states[$entity])
            );
        }
    }

    private function addPrimaryCondition(DbalQueryBuilderAlias $query, array $primaryKeys): void
    {
        $all = [];
        $i = 0;
        foreach ($primaryKeys as $primaryKey) {
            $where = [];

            foreach ($primaryKey as $field => $value) {
                ++$i;
                $field = EntityDefinitionQueryHelper::escape($field);
                $where[] = $field . ' = :param' . $i;
                $query->setParameter('param' . $i, $value);
            }

            $all[] = implode(' AND ', $where);
        }

        $query->andWhere(implode(' OR ', $all));
    }

    private function calculateChangeSet(WriteCommand $command, array $states): ChangeSet
    {
        foreach ($states as $state) {
            // check if current loop matches the command primary key
            $primaryKey = array_intersect($command->getPrimaryKey(), $state);

            if (\count(array_diff_assoc($command->getPrimaryKey(), $primaryKey)) === 0) {
                return new ChangeSet($state, $command->getPayload(), $command instanceof DeleteCommand);
            }
        }

        return new ChangeSet([], [], $command instanceof DeleteCommand);
    }

    private function getPlaceholders(int $columnCount, int $tupleCount): string
    {
        if ($columnCount > 1) {
            // multi column pk. Example: (product_id, language_id) IN ((p1, l1), (p2, l2), (px,lx),...)
            $tupleStr = '(?' . str_repeat(',?', $columnCount - 1) . ')';
        } else {
            // single column pk. Example: category_id IN (c1, c2, c3,...)
            $tupleStr = '?';
        }

        return $tupleStr . str_repeat(',' . $tupleStr, $tupleCount - 1);
    }

    private function getParentField(EntityDefinition $definition): ?FkField
    {
        if (!$definition->isInheritanceAware()) {
            return null;
        }

        /** @var ManyToOneAssociationField|null $parent */
        $parent = $definition->getFields()->get('parent');

        if (!$parent) {
            throw new ParentFieldNotFoundException($definition);
        }

        if (!$parent instanceof ManyToOneAssociationField) {
            throw new InvalidParentAssociationException($definition, $parent);
        }

        $fk = $definition->getFields()->getByStorageName($parent->getStorageName());

        if (!$fk) {
            throw new CanNotFindParentStorageFieldException($definition);
        }
        if (!$fk instanceof FkField) {
            throw new ParentFieldForeignKeyConstraintMissingException($definition, $fk);
        }

        return $fk;
    }

    private function getCurrentState(EntityDefinition $definition, array $primaryKey, WriteCommandQueue $commandQueue): array
    {
        $commands = $commandQueue->getCommandsForEntity($definition, $primaryKey);

        $useDatabase = true;

        $state = [];

        foreach ($commands as $command) {
            if ($command instanceof DeleteCommand) {
                $state = [];
                $useDatabase = false;

                continue;
            }

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $state = array_replace_recursive($state, $command->getPayload());

            if ($command instanceof InsertCommand) {
                $useDatabase = false;
            }
        }

        if (!$useDatabase) {
            return $state;
        }

        $hexPrimaryKey = Uuid::fromBytesToHexList($primaryKey);

        $currentState = $this->primaryKeyBag === null ? null : $this->primaryKeyBag->getExistenceState($definition, $hexPrimaryKey);
        if ($currentState === null) {
            $currentState = $this->fetchFromDatabase($definition, $primaryKey);
        }

        $parent = $this->getParentField($definition);

        if ($parent && \array_key_exists('parent', $currentState)) {
            $currentState[$parent->getStorageName()] = $currentState['parent'];
            unset($currentState['parent']);
        }

        return array_replace_recursive($currentState, $state);
    }

    private function fetchFromDatabase(EntityDefinition $definition, array $primaryKey): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->from(EntityDefinitionQueryHelper::escape($definition->getEntityName()));

        $fields = $definition->getPrimaryKeys();

        /** @var Field&StorageAware $field */
        foreach ($fields as $field) {
            if (!\array_key_exists($field->getStorageName(), $primaryKey)) {
                if (!\array_key_exists($field->getPropertyName(), $primaryKey)) {
                    throw new PrimaryKeyNotProvidedException($definition, $field);
                }

                $primaryKey[$field->getStorageName()] = $primaryKey[$field->getPropertyName()];
                unset($primaryKey[$field->getPropertyName()]);
            }

            $param = 'param_' . Uuid::randomHex();
            $query->andWhere(EntityDefinitionQueryHelper::escape($field->getStorageName()) . ' = :' . $param);
            $query->setParameter($param, $primaryKey[$field->getStorageName()]);
        }

        $query->addSelect('1 as `exists`');

        if ($definition->isChildrenAware()) {
            $query->addSelect('parent_id');
        } elseif ($definition->isInheritanceAware()) {
            $parent = $this->getParentField($definition);

            if ($parent !== null) {
                $query->addSelect(
                    EntityDefinitionQueryHelper::escape($parent->getStorageName())
                    . ' as `parent`'
                );
            }
        }

        $exists = $query->execute()->fetch(FetchMode::ASSOCIATIVE);
        if (!$exists) {
            $exists = [];
        }

        return $exists;
    }

    private function isChild(EntityDefinition $definition, array $data, array $state, array $primaryKey, WriteCommandQueue $commandQueue): bool
    {
        if ($definition instanceof EntityTranslationDefinition) {
            return $this->isTranslationChild($definition, $primaryKey, $commandQueue);
        }

        if (!$definition->isInheritanceAware()) {
            return false;
        }

        /** @var Field&StorageAware $fk */
        $fk = $this->getParentField($definition);
        //foreign key provided, !== null has parent otherwise not
        if (\array_key_exists($fk->getPropertyName(), $data)) {
            return isset($data[$fk->getPropertyName()]);
        }

        /** @var Field $association */
        $association = $definition->getFields()->get('parent');
        if (isset($data[$association->getPropertyName()])) {
            return true;
        }

        return isset($state[$fk->getStorageName()]);
    }

    private function wasChild(EntityDefinition $definition, array $state): bool
    {
        if (!$definition->isInheritanceAware()) {
            return false;
        }

        $fk = $this->getParentField($definition);

        return $fk !== null && isset($state[$fk->getStorageName()]);
    }

    private function isTranslationChild(EntityTranslationDefinition $definition, array $primaryKey, WriteCommandQueue $commandQueue): bool
    {
        $parent = $definition->getParentDefinition();

        if (!$parent->isInheritanceAware()) {
            return false;
        }

        /** @var FkField $fkField */
        $fkField = $definition->getFields()->getByStorageName(
            $parent->getEntityName() . '_id'
        );
        $parentPrimaryKey = [
            'id' => $primaryKey[$fkField->getStorageName()],
        ];

        if ($parent->isVersionAware()) {
            $parentPrimaryKey['versionId'] = $primaryKey[$parent->getEntityName() . '_version_id'];
        }

        $existence = $this->getExistence($parent, $parentPrimaryKey, [], $commandQueue);

        return $existence->isChild();
    }
}
