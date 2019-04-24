<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Struct\ArrayEntity;

class DefinitionValidator
{
    private const IGNORE_FIELDS = [
        'product.cover',
        'order_line_item.cover',
        'customer.defaultBillingAddress',
        'customer.defaultShippingAddress',
        'customer.activeShippingAddress',
        'customer.activeBillingAddress',
        'product_configurator_setting.selected',
    ];

    private const FOREIGN_KEY_PREFIX = 'fk';

    /**
     * @var DefinitionRegistry
     */
    protected $registry;

    private static $pluralExceptions = [
        'children', 'categoriesRo', 'properties', 'media',
    ];

    private static $customPrefixedNames = [
        'username', 'customerNumber', 'taxRate',
    ];

    private static $customShortNames = [
        'property_group' => 'group',
        'property_group_option' => 'option',
        'version_commit' => 'commit',
    ];

    private static $ignoredInPrefixCheck = [
        'properties', 'options', 'translationcode', 'blocks',
    ];

    private static $tablesWithoutDefinition = [
        'schema_version',
        'search_dictionary',
        'cart',
        'migration',
        'sales_channel_api_context',
    ];

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(DefinitionRegistry $registry, Connection $connection)
    {
        $this->registry = $registry;
        $this->connection = $connection;
        $this->connection->getEventManager()->addEventListener(Events::onSchemaIndexDefinition, new SchemaIndexListener());
    }

    public function validate()
    {
        $violations = [];

        /** @var string|EntityDefinition $definition */
        foreach ($this->registry->getDefinitions() as $definition) {
            $violations[$definition] = [];
        }

        foreach ($this->registry->getDefinitions() as $definition) {
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

            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                $violations = array_merge_recursive($violations, $this->validateEntityTranslationDefinitions($definition));
            }

            $violations = array_merge_recursive($violations, $this->validateSchema($definition));
        }

        $violations = array_filter($violations, function ($vio) {
            return !empty($vio);
        });

        return $violations;
    }

    public function getNotices(): array
    {
        $notices = [];
        /** @var string $definition */
        foreach ($this->registry->getDefinitions() as $definition) {
            $notices[$definition] = [];
        }

        foreach ($this->registry->getDefinitions() as $definition) {
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
        }

        $tableSchemas = $this->connection->getSchemaManager()->listTables();

        $tableViolations = $this->findNotRegisteredTables($tableSchemas);
        $namingViolations = $this->checkNaming($tableSchemas);

        $notices = array_merge_recursive($notices, $namingViolations, $tableViolations);

        return array_filter($notices, function ($vio) {
            return !empty($vio);
        });
    }

    /**
     * @param Table[] $tables
     */
    private function findNotRegisteredTables(array $tables): array
    {
        $violations = [];

        foreach ($tables as $table) {
            if (\in_array($table->getName(), self::$tablesWithoutDefinition, true)) {
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

    /**
     * @param Table[] $tables
     */
    private function checkNaming(array $tables): array
    {
        $fkViolations = [];

        foreach ($tables as $table) {
            if (\in_array($table->getName(), self::$tablesWithoutDefinition, true)) {
                continue;
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                if ($foreignKey->getNamespaceName() !== self::FOREIGN_KEY_PREFIX) {
                    $fkViolations[] = sprintf(
                        'Table %s has an invalid foreign key. Foreign keys have to start with fk.',
                        $table->getName()
                    );
                }

                if ($foreignKey->getNamespaceName() === null) {
                    continue;
                }

                $name = substr($foreignKey->getName(), strlen($foreignKey->getNamespaceName()) + 1);

                if ($name !== $table->getName()) {
                    $fkViolations[] = sprintf(
                        'Table %s has an invalid foreign key. Foreign keys format: fk.table_name.column_name',
                        $table->getName()
                    );
                }
            }
        }

        return ['Foreign key naming issues' => $fkViolations];
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function findStructNotices(string $struct, string $definition): array
    {
        $reflection = new \ReflectionClass($struct);

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

            if (\in_array($property->getName(), ['id', 'extensions'], true)) {
                continue;
            }

            if (!$fields->get($property->getName())) {
                $notices[] = sprintf('Missing field %s in %s', $property->getName(), $definition);
            }
        }

        return $notices;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function validateStruct(string $struct, string $definition): array
    {
        $reflection = new \ReflectionClass($struct);

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

            if (!$field->is(Deferred::class) && !$reflection->hasMethod($setter)) {
                $functionViolations[] = sprintf('No setter function for property %s in %s', $propertyName, $struct);
            }
        }

        return array_merge($properties, $functionViolations);
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function validateAssociations(string $definition): array
    {
        $violations = [];

        $associations = $definition::getFields()->filterInstance(AssociationField::class);

        $instance = new $definition();

        if ($instance instanceof MappingEntityDefinition) {
            return [];
        }

        /** @var AssociationField $association */
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

                if ($association instanceof TranslationsAssociationField) {
                    $violations = array_merge_recursive(
                        $violations,
                        $this->validateTranslationAssociation($definition, $association->getReferenceClass())
                    );
                }

                continue;
            }

            if ($association instanceof OneToOneAssociationField) {
                $violations = array_merge_recursive(
                    $violations,
                    $this->validateOneToOne($definition, $association)
                );
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

    private function validateEntityTranslationDefinitions(string $translationDefinition): array
    {
        $violations = [];

        $parentDefinitionClass = $translationDefinition::getParentDefinitionClass();
        if (!\class_exists($parentDefinitionClass)) {
            $violations[$translationDefinition] = sprintf('The getParentDefinitionClass `%s` for EntityTranslationDefinition `%s` does not exists', $parentDefinitionClass, $translationDefinition);
        }

        $translationsAssociationFields = $parentDefinitionClass::getFields()
            ->filterInstance(TranslationsAssociationField::class)
            ->filter(function (TranslationsAssociationField $f) use ($translationDefinition) {
                return $f->getReferenceClass() === $translationDefinition;
            })->getElements();

        if (empty($translationsAssociationFields)) {
            $violations[$parentDefinitionClass] = sprintf('The parentDefinitionClass `%s` for `%s` should define a `TranslationsAssociationField for `%s`. The parentDefinitionClass could be wrong too.', $parentDefinitionClass, $translationDefinition, $translationDefinition);
        }

        return $violations;
    }

    private function validateTranslationAssociation(string $parentDefinition, string $translationDefinition): array
    {
        $translatedFieldsInParent = array_keys($parentDefinition::getFields()->filterInstance(TranslatedField::class)->getElements());

        $translatedFields = array_keys($translationDefinition::getFields()->filter(function (Field $f) {
            return !$f->is(PrimaryKey::class)
                && !$f instanceof AssociationField
                && !in_array($f->getPropertyName(), ['createdAt', 'updatedAt'], true);
        })->getElements());

        $violations = [];

        $onlyParent = array_diff($translatedFieldsInParent, $translatedFields);
        foreach ($onlyParent as $propertyName) {
            $violations[$translationDefinition] = sprintf('Field `%s` defined in `%s`, but missing in `%s`', $propertyName, $parentDefinition, $translationDefinition);
        }

        $onlyTranslated = array_diff($translatedFields, $translatedFieldsInParent);
        foreach ($onlyTranslated as $propertyName) {
            $violations[$parentDefinition] = sprintf('Field `%s` defined in `%s`, but missing in `%s`. Please add `new TranslatedField(\'%s\') to `%s`', $propertyName, $translationDefinition, $parentDefinition, $propertyName, $parentDefinition);
        }

        return $violations;
    }

    private function validateOneToOne(string $definition, OneToOneAssociationField $association): array
    {
        $reference = $association->getReferenceClass();

        $associationViolations = [];

        $reverseSide = $reference::getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof OneToOneAssociationField) {
                    return false;
                }
                $reference = $field->getReferenceClass();

                return $field->getStorageName() === $association->getReferenceField() && $reference === $definition;
            }
        )->first();

        /** @var OneToOneAssociationField $reverseSide */
        if (!$reverseSide) {
            $associationViolations[$definition][] = sprintf(
                'Missing reverse one to one association for %s <-> %s (%s)',
                $definition,
                $association->getReferenceClass(),
                $association->getPropertyName()
            );
        }

        if ($association->is(CascadeDelete::class) && $reverseSide->is(CascadeDelete::class)) {
            $associationViolations[$definition][] = sprintf(
                'Remove cascade delete in definition %s association: %s. One to One association should only have one side defined cascade delete flag',
                $definition,
                $association->getPropertyName()
            );
        }

        return $associationViolations;
    }

    private function validateManyToOne(string $definition, ManyToOneAssociationField $association): array
    {
        $reference = $association->getReferenceClass();

        $associationViolations = [];

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

        if ($association->getFlag(CascadeDelete::class)) {
            $associationViolations[$definition][] = sprintf(
                'Remove cascade delete in definition %s association: %s. Many to one association should not have a cascade delete',
                $definition,
                $association->getPropertyName()
            );
        }

        return $associationViolations;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function validateOneToMany(string $definition, OneToManyAssociationField $association): array
    {
        $reference = $association->getReferenceClass();

        $associationViolations = $this->validateIsPlural($definition, $association);

        $reverseSide = $reference::getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof ManyToOneAssociationField) {
                    return false;
                }

                return $field->getStorageName() === $association->getReferenceField() && $field->getReferenceClass() === $definition;
            }
        )->first();

        $foreignKey = $reference::getFields()->getByStorageName($association->getReferenceField());

        if (!$foreignKey instanceof FkField) {
            $associationViolations[$definition][] = sprintf(
                'Missing reference foreign key for column %s for definition association %s.%s',
                $association->getReferenceField(),
                $definition::getEntityName(),
                $association->getPropertyName()
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

        if ($fk->getReferenceClass() !== $association->getReferenceDefinition()) {
            $violations[$definition][] = sprintf(
                'Reference column %s of field %s should map to definition %s',
                $fk->getPropertyName(),
                $association->getPropertyName(),
                $association->getReferenceDefinition()
            );
        }

        /** @var FkField $localColumn */
        $localColumn = $mapping::getFields()->getByStorageName($association->getMappingLocalColumn());
        if ($localColumn->getReferenceClass() !== $definition) {
            $violations[$definition][] = sprintf(
                'Local column %s of field %s should map to definition %s',
                $localColumn->getPropertyName(),
                $association->getPropertyName(),
                $definition
            );
        }

        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $reference::isVersionAware()) {
            $versionField = $mapping::getFields()->filter(function (Field $field) use ($definition) {
                return $field instanceof ReferenceVersionField && $field->getVersionReference() === $definition;
            })->first();

            if (!$versionField) {
                $violations[$mapping][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $definition, $mapping);
            }

            $referenceVersionField = $mapping::getFields()->filter(function (Field $field) use ($reference) {
                return $field instanceof ReferenceVersionField && $field->getVersionReference() === $reference;
            })->first();

            if (!$referenceVersionField) {
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

        return $violations;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function validateSchema(string $definition): array
    {
        $manager = $this->connection->getSchemaManager();

        $columns = $manager->listTableColumns($definition::getEntityName());

        $violations = [];

        /** @var Column $column */
        foreach ($columns as $column) {
            if ($this->isVersionIdFieldMappedByFkField($definition, $column)) {
                continue;
            }

            $field = $definition::getFields()->getByStorageName($column->getName());

            if ($field) {
                continue;
            }

            /** @var Field $association */
            $association = $definition::getFields()->get($column->getName());

            if ($association instanceof AssociationField && $association->is(Inherited::class)) {
                continue;
            }

            $violations[] = sprintf(
                'Column %s has no configured field',
                $column->getName()
            );
        }

        return [$definition => $violations];
    }

    private function validateIsPlural(string $definition, AssociationField $association): array
    {
        if (!$association instanceof ManyToManyAssociationField && !$association instanceof OneToManyAssociationField) {
            return [];
        }

        $propName = $association->getPropertyName();
        if (substr($propName, -1) === 's' || \in_array($propName, self::$pluralExceptions, true)) {
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

    private function validateReferenceNameContainedInName(string $definition, AssociationField $association): array
    {
        if ($definition === $association->getReferenceClass()) {
            return [];
        }
        $prop = $association->getPropertyName();

        if (\in_array(strtolower($prop), self::$ignoredInPrefixCheck, true)) {
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
                || $field instanceof OneToOneAssociationField
                || $field instanceof OneToManyAssociationField) {
                continue;
            }

            if (!$field instanceof Field) {
                continue;
            }

            if (\in_array($field->getPropertyName(), self::$customPrefixedNames, true)) {
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

    private function isVersionIdFieldMappedByFkField(string $definition, Column $column): bool
    {
        $fkFieldName = preg_replace('/_version_id$/i', '_id', $column->getName());

        $field = $definition::getFields()->getByStorageName($fkFieldName);

        if ($field instanceof FkField) {
            return true;
        }

        return false;
    }
}
