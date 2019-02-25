<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;

class EntityWriteGateway implements EntityWriteGatewayInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var WriteCommandValidatorInterface
     */
    private $commandQueueValidator;

    public function __construct(Connection $connection, WriteCommandValidatorInterface $commandQueueValidator)
    {
        $this->connection = $connection;
        $this->commandQueueValidator = $commandQueueValidator;
    }

    /**
     * {@inheritdoc}
     */
    public function getExistence(string $definition, array $primaryKey, array $data, WriteCommandQueue $commandQueue): EntityExistence
    {
        $state = $this->getCurrentState($definition, $primaryKey, $commandQueue);

        $exists = !empty($state);

        $isChild = $this->isChild($definition, $data, $state);

        $wasChild = $this->wasChild($definition, $state);

        return new EntityExistence($definition, $primaryKey, $exists, $isChild, $wasChild, $state);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $commands, WriteContext $context): void
    {
        $this->connection->transactional(function () use ($commands, $context) {
            // throws exception on violation and then aborts/rollbacks this transaction
            $this->commandQueueValidator->preValidate($commands, $context);

            foreach ($commands as $command) {
                $definition = $command->getDefinition();
                /** @var string|EntityDefinition $definition */
                $table = $definition::getEntityName();

                if ($command instanceof DeleteCommand) {
                    $this->connection->delete(EntityDefinitionQueryHelper::escape($table), $command->getPrimaryKey());
                    continue;
                }

                if ($command instanceof JsonUpdateCommand) {
                    $this->executeJsonUpdate($command);
                    continue;
                }

                if ($command instanceof UpdateCommand) {
                    if (!$command->isValid()) {
                        continue;
                    }
                    $this->connection->update(EntityDefinitionQueryHelper::escape($table), $command->getPayload(), $command->getPrimaryKey());
                    continue;
                }

                if ($command instanceof InsertCommand) {
                    $this->connection->insert(
                        EntityDefinitionQueryHelper::escape($table),
                        $command->getPayload()
                    );
                    continue;
                }

                throw new \RuntimeException(sprintf('Command of class %s not supported', \get_class($command)));
            }
            // throws exception on violation and then aborts/rollbacks this transaction
            $this->commandQueueValidator->postValidate($commands, $context);
        });
    }

    private static function isAssociative(array $array): bool
    {
        foreach ($array as $key => $_) {
            if (!is_int($key)) {
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
        $query->update('`' . $command->getDefinition()::getEntityName() . '`');

        foreach ($command->getPayload() as $attribute => $value) {
            // add path and value for each attribute value pair
            $values[] = '$."' . $attribute . '"';
            if (is_array($value) || is_object($value)) {
                $values[] = \json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
                // does the same thing as CAST(?, json) but works on mariadb
                $identityValue = is_object($value) || self::isAssociative($value) ? '{}' : '[]';
                $sets[] = '?, JSON_MERGE("' . $identityValue . '", ?)';
            } else {
                $values[] = $value;
                $sets[] = '?, ?';
            }
        }

        $storageName = $command->getStorageName();
        $query->set(
            $storageName,
            sprintf('JSON_SET(IFNULL(%s, "{}"), %s)', $storageName, implode(', ', $sets))
        );

        $identifier = $command->getPrimaryKey();
        foreach ($identifier as $key => $value) {
            $query->andWhere($key . ' = ?');
        }
        $query->setParameters(array_merge($values, array_values($identifier)));
        $query->execute();
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return FkField
     */
    private function getParentField(string $definition): ?FkField
    {
        if (!$definition::isInheritanceAware()) {
            return null;
        }

        /** @var ManyToOneAssociationField|null $parent */
        $parent = $definition::getFields()->get('parent');

        if (!$parent) {
            throw new \RuntimeException(
                sprintf(
                    'Can not find parent property %s field for definition %s',
                    'parent',
                    $definition
                )
            );
        }

        if (!$parent instanceof ManyToOneAssociationField) {
            throw new \RuntimeException(
                sprintf(
                    'Parent property %s in definition %s expected to be an ManyToOneAssociationField got %s',
                    'parent',
                    $definition,
                    \get_class($parent)
                )
            );
        }

        $fk = $definition::getFields()->getByStorageName($parent->getStorageName());

        if (!$fk) {
            throw new \RuntimeException(
                sprintf(
                    'Can not find FkField for parent property %s in definition %s',
                    'parent',
                    $definition
                )
            );
        }
        if (!$fk instanceof FkField) {
            throw new \RuntimeException(
                sprintf(
                    'Foreign key property %s of parent association %s in definition %s expected to be an FkField got %s',
                    $fk->getPropertyName(),
                    'parent',
                    $definition,
                    \get_class($fk)
                )
            );
        }

        return $fk;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function getCurrentState(string $definition, array $primaryKey, WriteCommandQueue $commandQueue): array
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

        $database = $this->fetchFromDatabase($definition, $primaryKey);

        $parent = $this->getParentField($definition);

        if ($parent && array_key_exists('parent', $database)) {
            $database[$parent->getStorageName()] = $database['parent'];
            unset($database['parent']);
        }

        return array_replace_recursive($database, $state);
    }

    private function fetchFromDatabase(string $definition, array $primaryKey): array
    {
        /** @var string|EntityDefinition $definition */
        $query = $this->connection->createQueryBuilder();
        $query->from(EntityDefinitionQueryHelper::escape($definition::getEntityName()));

        $fields = $definition::getPrimaryKeys();

        /** @var StorageAware|Field $field */
        foreach ($fields as $field) {
            $key = $field->getStorageName();

            if (!array_key_exists($key, $primaryKey)) {
                $key = $field->getPropertyName();

                if (!array_key_exists($key, $primaryKey)) {
                    throw new \RuntimeException(
                        sprintf('Expected primary key field %s for definition %s not provided', $key, $definition)
                    );
                }

                $primaryKey[$field->getStorageName()] = $primaryKey[$field->getPropertyName()];
                unset($primaryKey[$field->getPropertyName()]);
            }

            $query->andWhere($field->getStorageName() . ' = :' . $field->getPropertyName());
            $query->setParameter($field->getPropertyName(), $primaryKey[$field->getStorageName()]);
        }

        $query->addSelect('1 as `exists`');

        if ($definition::isChildrenAware()) {
            $query->addSelect('parent_id');
        } elseif (!$definition::isInheritanceAware()) {
            $query->addSelect('1 as `exists`');
        } else {
            $parent = $this->getParentField($definition);

            $query->addSelect(
                EntityDefinitionQueryHelper::escape($parent->getStorageName())
                . ' as `parent`'
            );
        }

        $exists = $query->execute()->fetch(FetchMode::ASSOCIATIVE);
        if ($exists) {
            return $exists;
        }

        return [];
    }

    private function isChild(string $definition, array $data, array $state): bool
    {
        /** @var EntityDefinition $definition */
        if (!$definition::isInheritanceAware()) {
            return false;
        }

        $fk = $this->getParentField($definition);
        //foreign key provided, !== null has parent otherwise not
        if (array_key_exists($fk->getPropertyName(), $data)) {
            return isset($data[$fk->getPropertyName()]);
        }

        $association = $definition::getFields()->get('parent');
        if (isset($data[$association->getPropertyName()])) {
            return true;
        }

        return isset($state[$fk->getStorageName()]);
    }

    private function wasChild(string $definition, array $state): bool
    {
        /** @var EntityDefinition $definition */
        if (!$definition::isInheritanceAware()) {
            return false;
        }

        $fk = $this->getParentField($definition);

        return isset($state[$fk->getStorageName()]);
    }
}
