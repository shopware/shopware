<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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

    public function add(string $entity, string $identifier, WriteCommand $command): void
    {
        $hash = $entity . ':' . $identifier;

        $this->commands[$entity][] = $command;
        $this->entityCommands[$hash][] = $command;
    }

    public static function hashedPrimary(DefinitionInstanceRegistry $registry, WriteCommand $command): string
    {
        $decoded = self::decodeCommandPrimary($registry, $command);

        $string = json_encode($decoded, \JSON_THROW_ON_ERROR);

        return md5($string);
    }

    /**
     * @throws ImpossibleWriteOrderException
     *
     * @return list<WriteCommand>
     */
    public function getCommandsInOrder(DefinitionInstanceRegistry $registry): array
    {
        $mapping = [];

        $foreignKeys = [];

        foreach ($this->commands as $entity => $grouped) {
            $definition = $registry->getByEntityName($entity);

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

                $key = $this->createPrimaryHash($entity, $this->getDecodedPrimaryKey($registry, $command));

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

                    $key = $this->createPrimaryHash($definition, $this->getDecodedPrimaryKey($registry, $command));
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
            $field = $definition->getFields()->get($fieldName) ?? $definition->getFields()->getByStorageName($fieldName);
            $decodedPrimaryKey[$fieldName] = $field ? $field->getSerializer()->decode($field, $fieldValue) : $fieldValue;
        }
        sort($decodedPrimaryKey);

        $hash = $definition->getEntityName() . ':' . md5(json_encode($decodedPrimaryKey, \JSON_THROW_ON_ERROR));

        return $this->entityCommands[$hash] ?? [];
    }

    /**
     * @return array<int, mixed>
     */
    private static function decodeCommandPrimary(DefinitionInstanceRegistry $registry, WriteCommand $command): array
    {
        $primaryKey = $command->getPrimaryKey();

        sort($primaryKey);

        // don't access definition of command directly, goal is to get rid of definition inside DTOs
        $definition = $registry->getByEntityName($command->getDefinition()->getEntityName());

        $decoded = [];
        foreach ($primaryKey as $fieldValue) {
            $fieldName = array_search($fieldValue, $command->getPrimaryKey(), true);
            $field = null;
            if ($fieldName) {
                $field = $definition->getFields()->get($fieldName) ?? $definition->getFields()->getByStorageName($fieldName);
            }
            $decoded[] = $field ? $field->getSerializer()->decode($field, $fieldValue) : $fieldValue;
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function getDecodedPrimaryKey(DefinitionInstanceRegistry $registry, WriteCommand $command): array
    {
        $primaryKey = $command->getPrimaryKey();

        $definition = $registry->getByEntityName($command->getDefinition()->getEntityName());

        $mapped = [];
        foreach ($definition->getPrimaryKeys() as $key) {
            if ($key instanceof VersionField || $key instanceof ReferenceVersionField) {
                continue;
            }
            if (!$key instanceof StorageAware) {
                throw new \RuntimeException();
            }

            $mapped[] = (string) $key->getSerializer()->decode($key, $primaryKey[$key->getStorageName()]);
        }

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

            $fk = $fks[$key];
            // check if the payload field is a foreign key which we have to consider
            if (!$fk instanceof FkField || $value === null) {
                continue;
            }

            if ($fk->is(NoConstraint::class)) {
                continue;
            }

            // create a hash for the foreign key which are used for the mapping
            $primary = [$fk->getSerializer()->decode($fk, $value)];

            $hash = $this->createPrimaryHash((string) $fk->getReferenceEntity(), $primary);

            // check if the hash/primary isn't persisted yet
            if (isset($mapping[$hash])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $primary
     */
    private function createPrimaryHash(string $entity, array $primary): string
    {
        sort($primary);

        return $entity . '-' . \implode('-', $primary);
    }
}
