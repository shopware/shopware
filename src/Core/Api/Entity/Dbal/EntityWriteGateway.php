<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Write\Command\DeleteCommand;
use Shopware\Api\Entity\Write\Command\InsertCommand;
use Shopware\Api\Entity\Write\Command\UpdateCommand;
use Shopware\Api\Entity\Write\Command\WriteCommandQueue;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\EntityWriteGatewayInterface;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;

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

        return new EntityExistence($definition, $primaryKey, $exists, $isChild, $wasChild);
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
        if ($definition::getParentPropertyName() === null) {
            return null;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $definition::getFields()->get($definition::getParentPropertyName());

        if (!$parent) {
            throw new \RuntimeException(
                sprintf(
                    'Can not find parent property %s field for definition %s',
                    $definition::getParentPropertyName(),
                    $definition
                )
            );
        }

        if (!$parent instanceof ManyToOneAssociationField) {
            throw new \RuntimeException(
                sprintf(
                    'Parent property %s in definition %s expected to be an ManyToOneAssociationField got %s',
                    $definition::getParentPropertyName(),
                    $definition,
                    get_class($parent)
                )
            );
        }

        $fk = $definition::getFields()->getByStorageName($parent->getStorageName());

        if (!$fk) {
            throw new \RuntimeException(
                sprintf(
                    'Can not find FkField for parent property %s in definition %s',
                    $definition::getParentPropertyName(),
                    $definition
                )
            );
        }
        if (!$fk instanceof FkField) {
            throw new \RuntimeException(
                sprintf(
                    'Foreign key property %s of parent association %s in definition %s expected to be an FkField got %s',
                    $fk->getPropertyName(),
                    $definition::getParentPropertyName(),
                    $definition,
                    get_class($fk)
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

            /** @var $command InsertCommand|UpdateCommand */
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

        if (array_key_exists('parent', $database)) {
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

        if (!$definition::getParentPropertyName()) {
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
        if (!$definition::getParentPropertyName()) {
            return false;
        }

        $fk = $this->getParentField($definition);
        //foreign key provided, !== null has parent otherwise not
        if (array_key_exists($fk->getPropertyName(), $data)) {
            return isset($data[$fk->getPropertyName()]);
        }

        $association = $definition::getFields()->get($definition::getParentPropertyName());
        if (isset($data[$association->getPropertyName()])) {
            return true;
        }

        return isset($state[$fk->getStorageName()]);
    }

    private function wasChild(string $definition, array $state): bool
    {
        /** @var EntityDefinition $definition */
        if (!$definition::getParentPropertyName()) {
            return false;
        }

        $fk = $this->getParentField($definition);

        return isset($state[$fk->getStorageName()]);
    }
}
