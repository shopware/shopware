<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ImpossibleWriteOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
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

        $primaryKey = array_map(static function ($id) {
            return Uuid::fromBytesToHex($id);
        }, $primaryKey);

        $hash = $senderIdentification->getEntityName() . ':' . md5(json_encode($primaryKey, \JSON_THROW_ON_ERROR));

        $this->commands[$senderIdentification->getEntityName()][] = $command;

        $this->entityCommands[$hash][] = $command;
        $this->definitions[$senderIdentification->getEntityName()] = $senderIdentification;
    }

    /**
     * @throws ImpossibleWriteOrderException
     *
     * @return WriteCommand[]
     */
    public function getCommandsInOrder(): array
    {
        $commands = array_filter($this->commands);

        $order = [];

        $counter = 0;

        while (!empty($commands)) {
            ++$counter;

            if ($counter === 50) {
                throw new ImpossibleWriteOrderException(array_keys($commands));
            }

            foreach ($commands as $definition => $defCommands) {
                $dependencies = $this->hasDependencies($this->definitions[$definition], $commands);

                if (!empty($dependencies)) {
                    continue;
                }

                foreach ($defCommands as $command) {
                    $order[] = $command;
                }

                unset($commands[$definition]);
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
                throw new WriteTypeIntendException($definition, $class, \get_class($command));
            }
        }
    }

    public function getCommandsForEntity(EntityDefinition $definition, array $primaryKey): array
    {
        $primaryKey = array_map(static function ($id) {
            return Uuid::fromBytesToHex($id);
        }, $primaryKey);

        sort($primaryKey);

        $hash = $definition->getEntityName() . ':' . md5(json_encode($primaryKey, \JSON_THROW_ON_ERROR));

        return $this->entityCommands[$hash] ?? [];
    }

    private function hasDependencies(EntityDefinition $definition, array $commands): array
    {
        $fields = $definition->getFields()
            ->filter(static function (Field $field) use ($definition) {
                if ($field instanceof ManyToOneAssociationField) {
                    return true;
                }

                if (!$field instanceof OneToOneAssociationField) {
                    return false;
                }

                $storage = $definition->getFields()->getByStorageName($field->getStorageName());

                return $storage instanceof FkField;
            });

        $requiredToManyDefinitions = $definition->getFields()
            ->filterInstance(OneToManyAssociationField::class)
            ->fmap(function (OneToManyAssociationField $field) {
                /** @var Field $storage */
                $storage = $field->getReferenceDefinition()->getFields()->getByStorageName($field->getReferenceField());

                if (!$storage->is(Required::class)) {
                    return null;
                }

                return $field->getReferenceDefinition()->getEntityName();
            });

        $requiredToManyDefinitions = array_flip($requiredToManyDefinitions);

        $dependencies = [];

        /** @var ManyToOneAssociationField $dependency */
        foreach ($fields as $dependency) {
            $referenceDefinition = $dependency->getReferenceDefinition();

            //skip self references, this dependencies are resolved by the ChildrenAssociationField
            if ($referenceDefinition === $definition) {
                continue;
            }

            $class = $referenceDefinition->getEntityName();

            //check if many to one has pending commands
            if (!\array_key_exists($class, $commands)) {
                continue;
            }

            // if the current dependency is defined also defined as OneToManyAssociationField and is required in the ReferenceDefinition, skip
            // in this case the reference definition has a dependency on this definition
            if (\array_key_exists($class, $requiredToManyDefinitions)) {
                continue;
            }

            if (!empty($commands[$class])) {
                $dependencies[] = $class;
            }
        }

        return $dependencies;
    }
}
