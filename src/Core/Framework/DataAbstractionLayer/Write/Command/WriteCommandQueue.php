<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ImpossibleWriteOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;

class WriteCommandQueue
{
    /**
     * @var array
     */
    private $commands;

    /**
     * @param WriteCommandInterface[] ...$commands
     */
    public function __construct(WriteCommandInterface ...$commands)
    {
        $this->commands = $commands;
    }

    public function add(string $senderIdentification, WriteCommandInterface $command): void
    {
        $this->commands[$senderIdentification][] = $command;
    }

    /**
     * @throws ImpossibleWriteOrderException
     *
     * @return WriteCommandInterface[]
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
                $dependencies = $this->hasDependencies($definition, $commands);

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

    public function hasDependencies(string $definition, array $commands): array
    {
        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields()
            ->filter(function (Field $field) {
                return !$field->is(ReadOnly::class) && $field instanceof ManyToOneAssociationField;
            });

        $toManyDefinitions = $definition::getFields()
            ->filterInstance(OneToManyAssociationField::class)
            ->fmap(function (OneToManyAssociationField $field) {
                return $field->getReferenceClass();
            });

        $toManyDefinitions = array_flip($toManyDefinitions);

        $dependencies = [];

        /** @var ManyToOneAssociationField $dependency */
        /** @var FieldCollection $fields */
        foreach ($fields as $dependency) {
            $class = $dependency->getReferenceClass();

            //skip self references, this dependencies are resolved by the ChildrenAssociationField
            if ($class === $definition) {
                continue;
            }

            //check if many to one has pending commands
            if (!array_key_exists($class, $commands)) {
                continue;
            }

            //if the current dependency is defined also defined as OneToManyAssociationField, skip
            if (array_key_exists($class, $toManyDefinitions)) {
                continue;
            }

            /** @var string $class */
            if (!empty($commands[$class])) {
                $dependencies[] = $class;
            }
        }

        return $dependencies;
    }

    /**
     * @return WriteCommandInterface[][]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function ensureIs(string $definition, $class): void
    {
        $commands = $this->commands[$definition];

        foreach ($commands as $command) {
            if (!$command instanceof $class) {
                throw new WriteTypeIntendException(sprintf(
                    'Expected command for "%s" to be "%s". (Got: %s)',
                    $definition,
                    $class,
                    get_class($command)
                ));
            }
        }
    }

    /**
     * @param string $definition
     * @param array  $primaryKey
     *
     * @return WriteCommandInterface[]
     */
    public function getCommandsForEntity(string $definition, array $primaryKey): array
    {
        if (!isset($this->commands[$definition])) {
            return [];
        }

        $commands = $this->commands[$definition];

        $filtered = [];
        foreach ($commands as $command) {
            if ($command->getPrimaryKey() == $primaryKey) {
                $filtered[] = $command;
            }
        }

        return $filtered;
    }
}
