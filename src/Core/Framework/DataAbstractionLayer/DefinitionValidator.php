<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DefinitionValidator
{
    private const IGNORE_FIELDS = [
        'product.cover',
        'customer.defaultBillingAddress',
        'customer.defaultShippingAddress',
        'customer.activeShippingAddress',
        'customer.activeBillingAddress',
        'product_configurator.selected',
        'state_machine_state.fromTransitions',
        'state_machine_state.toTransitions',
        'state_machine_transition.fromState',
        'state_machine_transition.toState',
        'order.state',
        'order_delivery.state',
        'order_transaction.state',
    ];

    /**
     * @var DefinitionRegistry
     */
    protected $registry;

    private static $pluralExceptions = [
        'children', 'categoriesRo', 'datasheet',
    ];

    private static $customPrefixedNames = [
        'username', 'customerNumber', 'taxRate',
    ];

    private static $customShortNames = [
        'configuration_group' => 'group',
        'configuration_group_option' => 'option',
        'version_commit' => 'commit',
    ];

    private static $ignoredInPrefixCheck = [
        'datasheet', 'variations', 'translationcode',
    ];

    private static $tablesWithoutDefinition = [
        'schema_version',
        'search_dictionary',
        'cart',
        'migration',
        'storefront_api_context',
    ];

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(DefinitionRegistry $registry, Connection $connection)
    {
        $this->registry = $registry;
        $this->connection = $connection;
    }

    public function validate()
    {
        $violations = [];

        /** @var string|EntityDefinition $definition */
        foreach ($this->registry->getElements() as $definition) {
            $violations[$definition] = [];
        }

        foreach ($this->registry->getElements() as $definition) {
            $instance = new $definition();

            $struct = ArrayEntity::class;
            if (!$instance instanceof MappingEntityDefinition) {
                $struct = $definition::getEntityClass();
            }

            if ($struct !== ArrayEntity::class) {
                $violations[$definition] = array_merge(
                    $violations[$definition],
                    $this->validateStruct($struct, $definition)
                );
            }

            $violations = array_merge_recursive($violations, $this->validateAssociations($definition));

            $violations = array_merge_recursive($violations, $this->validateSchema($definition));
        }

        $violations = array_filter($violations, function ($vio) {
            return !empty($vio);
        });

        return $violations;
    }

    public function getNotices(ContainerInterface $container): array
    {
        $notices = [];
        /** @var string $definition */
        foreach ($this->registry->getElements() as $definition) {
            $notices[$definition] = [];
        }

        foreach ($this->registry->getElements() as $definition) {
            $instance = new $definition();

            if ($instance instanceof MappingEntityDefinition) {
                continue;
            }
            $struct = $definition::getEntityClass();

            if ($struct !== ArrayEntity::class) {
                $notices[$definition] = array_merge_recursive(
                    $notices[$definition],
                    $this->findStructNotices($struct, $definition)
                );
            }
            $notices[$definition] = array_merge_recursive(
                $notices[$definition],
                $this->validateDataFieldNotPrefixedByEntityName($definition)
            );

            $entityName = $definition::getEntityName();
            if (!$container->has($entityName . '.repository')) {
                $notices[$definition][] = sprintf('Missing repository for entity %s ', $definition);
            }
        }

        $notices = array_merge_recursive($notices, $this->findNotRegisteredTables());

        return array_filter($notices, function ($vio) {
            return !empty($vio);
        });
    }

    private function findNotRegisteredTables(): array
    {
        $tables = $this->connection->getSchemaManager()->listTables();

        $violations = [];

        foreach ($tables as $table) {
            if (\in_array($table->getName(), self::$tablesWithoutDefinition)) {
                continue;
            }
            try {
                $this->registry->get($table->getName());
            } catch (Exception\DefinitionNotFoundException $e) {
                $violations[] = sprintf(
                    'Table %s has no configured definition',
                    $table->getName()
                );
            }
        }

        return [DefinitionRegistry::class => $violations];
    }

    private function findStructNotices(string $struct, string $definition): array
    {
        $reflection = new \ReflectionClass($struct);

        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields();

        $notices = [];
        foreach ($reflection->getProperties() as $property) {
            $key = $definition::getEntityName() . '.' . $property->getName();
            if (\in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($reflection->getParentClass()->getName() === MappingEntityDefinition::class) {
                continue;
            }

            if (\in_array($property->getName(), ['id', 'extensions'])) {
                continue;
            }

            if (!$fields->get($property->getName())) {
                $notices[] = sprintf('Missing field %s in %s', $property->getName(), $definition);
            }
        }

        return $notices;
    }

    private function validateStruct(string $struct, string $definition): array
    {
        $reflection = new \ReflectionClass($struct);

        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields();

        $properties = [];
        $functionViolations = [];

        foreach ($fields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                continue;
            }

            $key = $definition::getEntityName() . '.' . $field->getPropertyName();
            if (\in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            $propertyName = $field->getPropertyName();

            $setter = 'set' . ucfirst($propertyName);
            $getterMethods = [
                'get' . ucfirst($propertyName),
            ];

            if ($field instanceof BoolField) {
                $getterMethods[] = 'is' . ucfirst($propertyName);
                $getterMethods[] = 'has' . ucfirst($propertyName);
                $getterMethods[] = 'has' . ucfirst(preg_replace('/^has/', '', $propertyName));
            }

            $hasGetter = false;

            if (!$reflection->hasProperty($propertyName)) {
                $properties[] = sprintf('Missing property %s in %s', $propertyName, $struct);
            }

            foreach ($getterMethods as $getterMethod) {
                if ($reflection->hasMethod($getterMethod)) {
                    $hasGetter = true;
                    break;
                }
            }

            if (!$hasGetter) {
                $functionViolations[] = sprintf('No getter function for property %s in %s', $propertyName, $struct);
            }

            if (!$field->is(Deferred::class) && !$field->is(ReadOnly::class) && !$reflection->hasMethod($setter)) {
                $functionViolations[] = sprintf('No setter function for property %s in %s', $propertyName, $struct);
            }
        }

        return array_merge($properties, $functionViolations);
    }

    private function validateAssociations(string $definition): array
    {
        $violations = [];

        /** @var string|EntityDefinition $definition */
        $associations = $definition::getFields()->filterInstance(AssociationInterface::class);

        $instance = new $definition();

        if ($instance instanceof MappingEntityDefinition) {
            return [];
        }

        /** @var AssociationInterface|Field $association */
        foreach ($associations as $association) {
            $key = $definition::getEntityName() . '.' . $association->getPropertyName();

            if ($association instanceof SearchKeywordAssociationField) {
                continue;
            }

            if (\in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($association->is(Extension::class)) {
                continue;
            }

            $violations = array_merge_recursive(
                $violations,
                $this->validateReferenceNameContainedInName($definition, $association)
            );

            if ($association instanceof OneToManyAssociationField) {
                $violations = array_merge_recursive(
                    $violations,
                    $this->validateOneToMany($definition, $association)
                );

                continue;
            }

            if ($association instanceof ManyToOneAssociationField) {
                $violations = array_merge_recursive(
                    $violations,
                    $this->validateManyToOne($definition, $association)
                );

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                $violations = array_merge_recursive(
                    $violations,
                    $this->validateManyToMany($definition, $association)
                );
            }
        }

        return $violations;
    }

    private function validateManyToOne(string $definition, ManyToOneAssociationField $association): array
    {
        $reference = $association->getReferenceClass();

        $associationViolations = [];

        /** @var string|EntityDefinition $definition */
        $reverseSide = $reference::getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof OneToManyAssociationField) {
                    return false;
                }
                $reference = $field->getReferenceClass();

                return $field->getLocalField() === $association->getReferenceField() && $reference === $definition;
            }
        )->first();

        /** @var OneToManyAssociationField $reverseSide */
        if (!$reverseSide) {
            $associationViolations[$definition][] = sprintf(
                'Missing reverse one to many association for %s <-> %s (%s)',
                $definition,
                $association->getReferenceClass(),
                $association->getPropertyName()
            );
        }

        if ($reverseSide && $association->loadInBasic() && $reverseSide->loadInBasic()) {
            $associationViolations[$definition][] = sprintf(
                'Circular load in basic violation for %s <-> %s (property: %s & property: %s)',
                $definition,
                $association->getReferenceClass(),
                $association->getPropertyName(),
                $reverseSide->getPropertyName()
            );
        }

        if ($association->getFlag(CascadeDelete::class)) {
            $associationViolations[$definition][] = sprintf(
                'Remove cascade delete in definition %s association: %s. Many to one association should not have a cascade delete',
                $definition,
                $association->getPropertyName()
            );
        }

        return $associationViolations;
    }

    private function validateOneToMany(string $definition, OneToManyAssociationField $association): array
    {
        $reference = $association->getReferenceClass();

        $associationViolations = $this->validateIsPlural($definition, $association);

        /** @var string|EntityDefinition $definition */
        $reverseSide = $reference::getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof ManyToOneAssociationField) {
                    return false;
                }

                return $field->getStorageName() === $association->getReferenceField() && $field->getReferenceClass() === $definition;
            }
        )->first();

        if (!$reverseSide && $definition !== CatalogDefinition::class) {
            $associationViolations[$definition][] = sprintf(
                'Association %s.%s has no reverse association in definition %s',
                $definition::getEntityName(),
                $association->getPropertyName(),
                $association->getReferenceClass()
            );
        }

        $foreignKey = $reference::getFields()->getByStorageName($association->getReferenceField());

        if (!$foreignKey instanceof FkField) {
            $associationViolations[$definition][] = sprintf(
                'Missing reference foreign key for column %s for definition association %s.%s',
                $association->getReferenceField(),
                $definition::getEntityName(),
                $association->getPropertyName()
            );
        }

        /** @var AssociationInterface $reverseSide */
        if ($reverseSide && $association->loadInBasic() && $reverseSide->loadInBasic()) {
            $associationViolations[$definition][] = sprintf(
                'Circular load in basic violation for %s <-> %s (property: %s & property: %s)',
                $definition,
                $association->getReferenceClass(),
                $association->getPropertyName(),
                $reverseSide->getPropertyName()
            );
        }

        return $associationViolations;
    }

    private function validateManyToMany(string $definition, ManyToManyAssociationField $association): array
    {
        $reference = $association->getReferenceDefinition();

        $violations = $this->validateIsPlural($definition, $association);

        $mapping = $association->getMappingDefinition();
        $column = $association->getMappingReferenceColumn();
        $fk = $mapping::getFields()->getByStorageName($column);

        if (!$fk) {
            $violations[$mapping][] = sprintf('Missing field %s in definition %s', $column, $mapping);
        }
        if ($fk && !$fk->is(PrimaryKey::class)) {
            $violations[$mapping][] = sprintf('Foreign key field %s in definition %s is not part of the primary key', $column, $mapping);
        }
        if ($fk && !$fk instanceof FkField) {
            $violations[$mapping][] = sprintf('Field %s in definition %s has to be defined as FkField', $column, $mapping);
        }

        $column = $association->getMappingReferenceColumn();
        $fk = $mapping::getFields()->getByStorageName($column);

        if (!$fk) {
            $violations[$mapping][] = sprintf('Missing field %s in definition %s', $column, $mapping);
        }
        if ($fk && !$fk->is(PrimaryKey::class)) {
            $violations[$mapping][] = sprintf('Foreign key field %s in definition %s is not part of the primary key', $column, $mapping);
        }
        if ($fk && !$fk instanceof FkField) {
            $violations[$mapping][] = sprintf('Field %s in definition %s has to be defined as FkField', $column, $mapping);
        }

        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware()) {
            $versionField = $mapping::getFields()->filter(function (Field $field) use ($definition) {
                return $field instanceof ReferenceVersionField && $field->getVersionReference() === $definition;
            })->first();

            if (!$versionField) {
                $violations[$mapping][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $definition, $mapping);
            }

            $referenceVersionField = $mapping::getFields()->filter(function (Field $field) use ($reference) {
                return $field instanceof ReferenceVersionField && $field->getVersionReference() === $reference;
            })->first();

            if ($reference::isVersionAware() && !$referenceVersionField) {
                $violations[$mapping][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $reference, $mapping);
            }
        }

        $reverse = $reference::getFields()->filter(function (Field $field) use ($definition, $association) {
            return $field instanceof ManyToManyAssociationField
                && $field->getReferenceDefinition() === $definition
                && $field->getMappingDefinition() === $association->getMappingDefinition();
        })->first();

        if (!$reverse) {
            $violations[$reference][] = sprintf('Missing reverse many to many association for original %s.%s', $definition, $association->getPropertyName());
        }

        /** @var AssociationInterface $reverse */
        if ($reverse && $association->loadInBasic() && $reverse->loadInBasic()) {
            $violations[$definition][] = sprintf(
                'Circular load in basic violation for %s <-> %s (property: %s & property: %s)',
                $definition,
                $association->getReferenceClass(),
                $association->getPropertyName(),
                $reverse->getPropertyName()
            );
        }

        return $violations;
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return array
     */
    private function validateSchema(string $definition): array
    {
        $manager = $this->connection->getSchemaManager();

        $columns = $manager->listTableColumns($definition::getEntityName());

        $violations = [];

        /** @var Column $column */
        foreach ($columns as $column) {
            $field = $definition::getFields()->getByStorageName($column->getName());

            if ($field) {
                continue;
            }

            /** @var Field $association */
            $association = $definition::getFields()->get($column->getName());

            if ($association instanceof AssociationInterface && $association->is(Inherited::class)) {
                continue;
            }

            $violations[] = sprintf(
                'Column %s has no configured field',
                $column->getName()
            );
        }

        return [$definition => $violations];
    }

    private function validateIsPlural(string $definition, AssociationInterface $association): array
    {
        if (!$association instanceof ManyToManyAssociationField && !$association instanceof OneToManyAssociationField) {
            return [];
        }

        $propName = $association->getPropertyName();
        if (substr($propName, -1) === 's' || \in_array($propName, self::$pluralExceptions)) {
            return [];
        }

        $ref = $this->getShortClassName($association->getReferenceClass());
        $def = $this->getShortClassName($definition);

        $ref = str_replace($def, '', $ref);
        $refPlural = Inflector::pluralize($ref);

        if (stripos($propName, $refPlural) === \strlen($propName) - \strlen($refPlural)) {
            return [];
        }

        return [$definition => [
                sprintf(
                    'Association %s.%s does not end with a \'s\'.',
                    $definition::getEntityName(),
                    $association->getPropertyName()
                ),
            ],
        ];
    }

    private function mapRefNameContainedName(string $ref): string
    {
        $normalized = strtolower(Inflector::tableize($ref));
        if (!isset(self::$customShortNames[$normalized])) {
            return $ref;
        }

        return self::$customShortNames[$normalized];
    }

    private function validateReferenceNameContainedInName(string $definition, AssociationInterface $association): array
    {
        if ($definition === $association->getReferenceClass()) {
            return [];
        }
        $prop = $association->getPropertyName();

        if (\in_array(strtolower($prop), self::$ignoredInPrefixCheck)) {
            return [];
        }

        $ref = $association instanceof ManyToManyAssociationField
            ? $association->getReferenceDefinition()
            : $association->getReferenceClass();

        $ref = $this->getShortClassName($ref);
        $def = $this->getShortClassName($definition);

        $ref = str_replace($def, '', $ref);

        $namespace = $this->getAggregateNamespace($definition);
        if ($namespace !== $ref) {
            $ref = str_replace($namespace, '', $ref);
        }

        $ref = $this->mapRefNameContainedName($ref);
        $refPlural = Inflector::pluralize($ref);

        if (stripos($prop, $ref) === false && stripos($prop, $refPlural) === false) {
            $ret = [$definition => [
                    sprintf(
                        'Association %s.%s does not contain reference class name `%s` or `%s`.',
                        $definition::getEntityName(),
                        $association->getPropertyName(),
                        $ref,
                        $refPlural
                    ),
                ],
            ];

            return $ret;
        }

        return [];
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function validateDataFieldNotPrefixedByEntityName(string $definition): array
    {
        $violations = [];

        foreach ($definition::getFields() as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField
                || $field instanceof ManyToOneAssociationField
                || $field instanceof OneToManyAssociationField) {
                continue;
            }

            if (!$field instanceof Field) {
                continue;
            }

            if (\in_array($field->getPropertyName(), self::$customPrefixedNames)) {
                continue;
            }

            // Skip fields where Entity class name is prefix of reference class name
            if ($field instanceof FkField) {
                $refClass = $field instanceof ReferenceVersionField
                    ? $field->getVersionReference()
                    : $field->getReferenceClass();

                $ref = $this->getShortClassName($refClass);
                $def = $this->getShortClassName($definition);

                if (stripos($ref, $def) === 0) {
                    continue;
                }
            }

            $entityNamePrefix = $definition::getEntityName() . '_';
            if (strpos($field->getStorageName(), $entityNamePrefix) === 0) {
                $violations[] = sprintf(
                    'Storage name `%s` is prefixed by entity name `%s`. Use storage name `%s` instead.',
                    $field->getStorageName(),
                    substr($entityNamePrefix, 0, -1),
                    substr($field->getStorageName(), \strlen($entityNamePrefix))
                );
            }

            $defPrefix = $this->getShortClassName($definition);
            if (strpos($field->getPropertyName(), $defPrefix) === 0) {
                $violations[] = sprintf(
                    'Property name `%s` is prefixed by struct name `%s`. Use property name `%s` instead',
                    $field->getPropertyName(),
                    $defPrefix,
                    lcfirst(substr($field->getPropertyName(), \strlen($defPrefix)))
                );
            }
        }

        return $violations;
    }

    private function getShortClassName(string $definition): string
    {
        return lcfirst(preg_replace('/.*\\\\([^\\\\]+)Definition/', '$1', $definition));
    }

    private function getAggregateNamespace(string $definition): string
    {
        return lcfirst(preg_replace('/.*\\\\([^\\\\]+)\\\\Aggregate.*/', '$1', $definition));
    }
}
