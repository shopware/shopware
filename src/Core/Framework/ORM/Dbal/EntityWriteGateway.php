<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Write\Command\DeleteCommand;
use Shopware\Core\Framework\ORM\Write\Command\InsertCommand;
use Shopware\Core\Framework\ORM\Write\Command\UpdateCommand;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;

class EntityWriteGateway implements EntityWriteGatewayInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
    public function execute(array $commands): void
    {
        $this->connection->transactional(function () use ($commands) {
            foreach ($commands as $command) {
                $definition = $command->getDefinition();
                /** @var string|EntityDefinition $definition */
                $table = $definition::getEntityName();

                if ($command instanceof DeleteCommand) {
                    $this->connection->delete(EntityDefinitionQueryHelper::escape($table), $command->getPrimaryKey());
                    continue;
                }

                if ($command instanceof UpdateCommand) {
                    $this->connection->update(
                        EntityDefinitionQueryHelper::escape($table),
                        $command->getPayload(),
                        $command->getPrimaryKey()
                    );
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
        });
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
     * @param array                   $primaryKey
     * @param WriteCommandQueue       $commandQueue
     *
     * @return array
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

    /**
     * @param string $definition
     * @param array  $primaryKey
     *
     * @return array
     */
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

        $exists = $query->execute()->fetch(\PDO::FETCH_ASSOC);
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
