<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ImpossibleWriteOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\NoConstraint;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class WriteCommandQueue
{
    /**
     * @var array<string, WriteCommand[]>
     */
    private array $commands = [];

    /**
     * @var array<string, WriteCommand[]>
     */
    private array $entityCommands = [];

    /**
     * @var EntityDefinition[]
     */
    private array $definitions = [];

    public function add(EntityDefinition $senderIdentification, WriteCommand $command): void
    {
        $primaryKey = $command->getPrimaryKey();

        sort($primaryKey);

        $decodedPrimaryKey = [];
        foreach ($primaryKey as $fieldValue) {
            /** @var string|false $fieldName */
            $fieldName = array_search($fieldValue, $command->getPrimaryKey(), true);
            /** @var Field|null $field */
            $field = null;
            if ($fieldName) {
                $field = $senderIdentification->getFields()->get($fieldName) ?? $senderIdentification->getFields()->getByStorageName($fieldName);
            }
            $decodedPrimaryKey[] = $field ? $field->getSerializer()->decode($field, $fieldValue) : $fieldValue;
        }

        $hash = $senderIdentification->getEntityName() . ':' . md5(json_encode($decodedPrimaryKey, \JSON_THROW_ON_ERROR));

        $this->commands[$senderIdentification->getEntityName()][] = $command;

        $this->entityCommands[$hash][] = $command;
        $this->definitions[$senderIdentification->getEntityName()] = $senderIdentification;
    }

    /**
     * @throws ImpossibleWriteOrderException
     *
     * @return list<WriteCommand>
     */
    public function getCommandsInOrder(): array
    {
        $mapping = [];

        $foreignKeys = [];

        foreach ($this->commands as $entity => $grouped) {
            $definition = $this->definitions[$entity];

            // we need a foreign key mapping later on
            foreach ($definition->getFields() as $field) {
                if (!$field instanceof FkField) {
                    continue;
                }
                $foreignKeys[$entity][$field->getStorageName()] = $field;
            }

            // now we create a primary key mapping which is used to identify if we have to insert some primaries first
            foreach ($grouped as $command) {
                if (!$command instanceof InsertCommand) {
                    continue;
                }

                $key = self::createPrimaryHash($entity, $this->getDecodedPrimaryKey($command));

                $mapping[$key] = true;
            }
        }

        $order = [];
        $commands = array_filter($this->commands);
        $counter = 0;

        while (!empty($commands)) {
            ++$counter;

            if ($counter === 50) {
                throw new ImpossibleWriteOrderException(array_keys($commands));
            }

            foreach ($commands as $definition => $defCommands) {
                foreach ($defCommands as $index => $command) {
                    $delay = $this->hasUnresolvedForeignKey($definition, $foreignKeys, $mapping, $command);

                    if ($delay) {
                        continue;
                    }

                    $key = self::createPrimaryHash($definition, $this->getDecodedPrimaryKey($command));
                    unset($mapping[$key]);

                    $order[] = $command;
                    unset($commands[$definition][$index]);

                    if (empty($commands[$definition])) {
                        unset($commands[$definition]);
                    }
                }
            }
        }

        return $order;
    }

    /**
     * @return array<string, WriteCommand[]>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @throws WriteTypeIntendException
     */
    public function ensureIs(EntityDefinition $definition, string $class): void
    {
        $commands = $this->commands[$definition->getEntityName()];

        foreach ($commands as $command) {
            if (!$command instanceof $class) {
                throw new WriteTypeIntendException($definition, $class, $command::class);
            }
        }
    }

    /**
     * @param array<string, string> $primaryKey
     *
     * @return WriteCommand[]
     */
    public function getCommandsForEntity(EntityDefinition $definition, array $primaryKey): array
    {
        $decodedPrimaryKey = [];
        foreach ($primaryKey as $fieldName => $fieldValue) {
            /** @var Field|null $field */
            $field = $definition->getFields()->get($fieldName) ?? $definition->getFields()->getByStorageName($fieldName);
            $decodedPrimaryKey[$fieldName] = $field ? $field->getSerializer()->decode($field, $fieldValue) : $fieldValue;
        }
        sort($decodedPrimaryKey);

        $hash = $definition->getEntityName() . ':' . md5(json_encode($decodedPrimaryKey, \JSON_THROW_ON_ERROR));

        return $this->entityCommands[$hash] ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function getDecodedPrimaryKey(WriteCommand $command): array
    {
        $primaryKey = $command->getPrimaryKey();

        $mapped = [];
        /** @var Field $key */
        foreach ($command->getDefinition()->getPrimaryKeys() as $key) {
            if ($key instanceof VersionField || $key instanceof ReferenceVersionField) {
                continue;
            }
            if (!$key instanceof StorageAware) {
                throw new \RuntimeException();
            }

            $mapped[$key->getStorageName()] = $key->getSerializer()->decode($key, $primaryKey[$key->getStorageName()]);
        }

        sort($mapped);

        return $mapped;
    }

    /**
     * @param array<string, array<string, FkField>>  $foreignKeys
     * @param array<string, bool> $mapping
     */
    private function hasUnresolvedForeignKey(string $entity, array $foreignKeys, array $mapping, WriteCommand $command): bool
    {
        // this definition has no foreign keys
        if (!isset($foreignKeys[$entity])) {
            return false;
        }

        // get access to all foreign keys of the definition
        $fks = $foreignKeys[$entity];

        // loop the command payload to check if there are foreign keys inside which are not persisted right now
        foreach ($command->getPayload() as $key => $value) {
            // no foreign key
            if (!isset($fks[$key])) {
                continue;
            }

            /** @var FkField $fk */
            $fk = $fks[$key];
            // check if the payload field is a foreign key which we have to consider
            if (!$fk instanceof FkField || $value === null) {
                continue;
            }

            if ($fk->is(NoConstraint::class)) {
                continue;
            }

            // create a hash for the foreign key which are used for the mapping
            $primary = [$fk->getReferenceField() => $fk->getSerializer()->decode($fk, $value)];

            $hash = self::createPrimaryHash((string) $fk->getReferenceEntity(), $primary);

            // check if the hash/primary isn't persisted yet
            if (isset($mapping[$hash])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $primary
     */
    private static function createPrimaryHash(string $entity, array $primary): string
    {
        sort($primary);

        return $entity . '-' . \implode('-', $primary);
    }
}
