<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ImpossibleWriteOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\Struct\Uuid;

class WriteCommandQueue
{
    /**
     * @var array
     */
    private $commands;

    /**
     * @var array[]
     */
    private $entityCommands = [];

    /**
     * @param WriteCommandInterface[] ...$commands
     */
    public function __construct(WriteCommandInterface ...$commands)
    {
        $this->commands = $commands;
    }

    public function add(string $senderIdentification, WriteCommandInterface $command): void
    {
        $primaryKey = $command->getPrimaryKey();

        sort($primaryKey);

        $primaryKey = array_map(function ($id) {
            return Uuid::fromBytesToHex($id);
        }, $primaryKey);

        $hash = $senderIdentification . ':' . md5(json_encode($primaryKey));

        $this->commands[$senderIdentification][] = $command;

        $this->entityCommands[$hash][] = $command;
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
                if ($field instanceof ManyToOneAssociationField) {
                    return true;
                }

                return $field instanceof OneToOneAssociationField && $field->is(CascadeDelete::class);
            });

        $toManyDefinitions = $definition::getFields()
            ->filterInstance(OneToManyAssociationField::class)
            ->fmap(function (OneToManyAssociationField $field) {
                return $field->getReferenceClass();
            });

        $toManyDefinitions = array_flip($toManyDefinitions);

        $dependencies = [];

        /** @var ManyToOneAssociationField $dependency */
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
     * @return WriteCommandInterface[]
     */
    public function getCommandsForEntity(string $definition, array $primaryKey): array
    {
        $primaryKey = array_map(function ($id) {
            return Uuid::fromBytesToHex($id);
        }, $primaryKey);

        sort($primaryKey);

        $hash = $definition . ':' . md5(json_encode($primaryKey));

        if (!isset($this->entityCommands[$hash])) {
            return [];
        }

        return $this->entityCommands[$hash];
    }
}
