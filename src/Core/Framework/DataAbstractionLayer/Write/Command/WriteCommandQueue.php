<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\System\Language\LanguageDefinition;

class WriteCommandQueue
{
    /**
     * @var string[]
     */
    private $registeredResources = [];

    /**
     * @var string[]
     */
    private $order = [];

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

    /**
     * @param string $definition
     */
    public function setOrder(string $definition): void
    {
        $identifierOrder = $this->getWriteOrder($definition);

        if (\in_array($definition, $this->registeredResources, true)) {
            return;
        }

        $this->order = $identifierOrder;

        $this->order = $this->moveTranslationAfterLanguage($this->order);

        foreach ($identifierOrder as $identifier) {
            $this->commands[$identifier] = [];
        }

        $this->registeredResources[] = $definition;
    }

    public function updateOrder(string $definition): void
    {
        $identifierOrder = $this->getWriteOrder($definition);

        if (\in_array($definition, $this->registeredResources, true)) {
            return;
        }

        $notAlreadyOrderedIdentifiers = [];
        foreach ($identifierOrder as $identifier) {
            if ($identifier === $definition || !\in_array($identifier, $this->order, true)) {
                $notAlreadyOrderedIdentifiers[] = $identifier;
            }
        }

        $localIndex = array_search($definition, $this->order);
        $this->order = array_merge(
            \array_slice($this->order, 0, $localIndex),
            $notAlreadyOrderedIdentifiers,
            \array_slice($this->order, $localIndex + 1)
        );

        $this->order = $this->moveTranslationAfterLanguage($this->order);

        foreach ($this->order as $identifier) {
            if (isset($this->commands[$identifier])) {
                continue;
            }

            $this->commands[$identifier] = [];
        }

        $this->registeredResources[] = $definition;
    }

    /**
     * @return string[]
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    public function add(string $senderIdentification, WriteCommandInterface $command): void
    {
        if (!\is_array($this->commands[$senderIdentification])) {
            throw new \InvalidArgumentException(sprintf('Unable to set write command for %s, it was not beforehand registered.', $senderIdentification));
        }

        $this->commands[$senderIdentification][] = $command;
    }

    /**
     * @return WriteCommandInterface[]
     */
    public function getCommandsInOrder(): array
    {
        $commands = array_filter($this->commands);

        $order = [];

        while (!empty($commands)) {
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
        $commands = $this->getCommandsInOrder();

        $filtered = [];
        foreach ($commands as $command) {
            if ($command->getDefinition() === $definition && $command->getPrimaryKey() == $primaryKey) {
                $filtered[] = $command;
            }
        }

        return $filtered;
    }

    /**
     * @param array $order
     *
     * @return array
     */
    private function moveTranslationAfterLanguage(array $order): array
    {
        $flipped = \array_flip($order);

        if (!isset($flipped[LanguageDefinition::class])) {
            return $order;
        }

        $translations = [];

        /** @var string|EntityDefinition $definition */
        foreach ($order as $index => $definition) {
            $translations[$definition::getTranslationDefinitionClass()] = 1;
        }

        $translations = array_intersect_key($flipped, $translations);
        foreach ($translations as $definition => $index) {
            unset($order[$index]);
        }

        $order = array_values($order);

        foreach ($translations as $definition => $index) {
            $order[] = $definition;
        }

        return $order;
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return array
     */
    private function getWriteOrder(string $definition): array
    {
        $associations = $definition::getFields()->filter(function (Field $field) {
            return $field instanceof AssociationInterface && !$field->is(ReadOnly::class);
        });

        $manyToOne = $definition::filterAssociationReferences(ManyToOneAssociationField::class, $associations);

        $oneToMany = $definition::filterAssociationReferences(OneToManyAssociationField::class, $associations);

        $manyToMany = $definition::filterAssociationReferences(ManyToManyAssociationField::class, $associations);

        $self = array_filter([$definition, $definition::getTranslationDefinitionClass()]);

        /*
         * If a linked entity exists once as OneToMany but also as ManyToOne (bi-directional foreign keys),
         * it must be treated as OneToMany. In the MySQL database,
         * no foreign key may be created for the ManyToOne relation.
         *
         * Examples:
         *      a customer has 1:N addresses
         *      a customer has 1:1 default_shipping_address
         *      a customer has 1:1 default_billing_address
         */
        $c = array_intersect($manyToOne, $oneToMany);
        foreach ($c as $index => $value) {
            unset($manyToOne[$index]);
        }

        return array_unique(array_values(array_merge($manyToOne, $self, $oneToMany, $manyToMany)));
    }
}
