<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Schema\Table;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Struct\ArrayEntity;

class DefinitionValidator
{
    private const FOREIGN_KEY_PREFIX = 'fk';

    private const IGNORE_FIELDS = [
        'product.cover',
        'order_line_item.cover',
        'customer.defaultBillingAddress',
        'customer.defaultShippingAddress',
        'customer.activeShippingAddress',
        'customer.activeBillingAddress',
        'product_configurator_setting.selected',
        'sales_channel.wishlists',
        'product.wishlists',
        'order.billingAddress',
        'order.createdBy',
        'order.updatedBy',
        'product_search_config.excludedTerms',
        'integration.writeAccess',
    ];

    private const PLURAL_EXCEPTIONS = [
        'children', 'categoriesRo', 'properties', 'media',
    ];

    private const CUSTOM_PREFIXED_NAMED = [
        'username',
        'customerNumber',
        'taxRate',
        'orderNumber',
        'orderDate',
        'productNumber',
        'mediaType',
        'mediaTypeRaw',
        'salutationKey',
        'scheduledTaskClass',
    ];

    private const CUSTOM_SHORT_NAMES = [
        'property_group' => 'group',
        'property_group_option' => 'option',
        'version_commit' => 'commit',
    ];

    private const INGNORED_IN_PREFIX_CHECK = [
        'properties', 'options', 'translationcode', 'sections', 'logo',
    ];

    private const TABLES_WITHOUT_DEFINITION = [
        'schema_version',
        'cart',
        'migration',
        'sales_channel_api_context',
    ];

    private const IGNORED_ENTITY_PROPERTIES = [
        'id',
        'extensions',
        '_uniqueIdentifier',
        'versionId',
        'translated',
        'createdAt',
        'updatedAt',
    ];

    private const GENERIC_FK_FIELDS = [
        'seo_url.foreignKey',
    ];

    private const DELETE_FLAG_TO_ACTION_MAPPING = [
        CascadeDelete::class => ['CASCADE'],
        RestrictDelete::class => ['RESTRICT', null, false],
        SetNullOnDelete::class => ['SET NULL'],
    ];

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(DefinitionInstanceRegistry $registry, Connection $connection)
    {
        $this->registry = $registry;
        $this->connection = $connection;
        $this->connection->getEventManager()->addEventListener(Events::onSchemaIndexDefinition, new SchemaIndexListener());
    }

    public function validate(): array
    {
        $violations = [];

        foreach ($this->registry->getDefinitions() as $definition) {
            // ignore definitions from a test namespace
            if (preg_match('/.*\\\\Test\\\\.*/', $definition->getClass())) {
                continue;
            }
            $violations[$definition->getClass()] = [];

            $violations = array_merge_recursive($violations, $this->validateSchema($definition));

            $violations = array_merge_recursive($violations, $this->checkEntityNameConstant($definition));

            $struct = ArrayEntity::class;
            if (!$definition instanceof MappingEntityDefinition) {
                $struct = $definition->getEntityClass();
            }

            if ($struct !== ArrayEntity::class) {
                $violations[$definition->getClass()] = array_merge(
                    $violations[$definition->getClass()],
                    $this->validateStruct($struct, $definition)
                );
            }

            $violations = array_merge_recursive($violations, $this->validateAssociations($definition));

            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                $violations = array_merge_recursive($violations, $this->validateEntityTranslationGettersAreNullable($definition));
                $violations = array_merge_recursive($violations, $this->validateEntityTranslationDefinitions($definition));
            }
        }

        $violations = array_filter($violations, function ($vio) {
            return !empty($vio);
        });

        return $violations;
    }

    public function getNotices(): array
    {
        $notices = [];
        foreach ($this->registry->getDefinitions() as $definition) {
            $notices[$definition->getClass()] = [];
        }

        foreach ($this->registry->getDefinitions() as $definition) {
            if ($definition instanceof MappingEntityDefinition) {
                continue;
            }

            $notices = array_merge_recursive($notices, $this->validateColumn($definition));

            $struct = $definition->getEntityClass();

            if ($struct !== ArrayEntity::class) {
                $notices[$definition->getClass()] = array_merge_recursive(
                    $notices[$definition->getClass()],
                    $this->findEntityNotices($struct, $definition)
                );
            }

            $notices[$definition->getClass()] = array_merge_recursive(
                $notices[$definition->getClass()],
                $this->validateDataFieldNotPrefixedByEntityName($definition)
            );

            $notices[$definition->getClass()] = array_merge_recursive(
                $notices[$definition->getClass()],
                $this->checkParentDefinition($definition)
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
            if (\in_array($table->getName(), self::TABLES_WITHOUT_DEFINITION, true)) {
                continue;
            }

            try {
                $this->registry->getByEntityName($table->getName());
            } catch (DefinitionNotFoundException $e) {
                $violations[] = sprintf(
                    'Table %s has no configured definition',
                    $table->getName()
                );
            }
        }

        return [DefinitionInstanceRegistry::class => $violations];
    }

    /**
     * @param Table[] $tables
     */
    private function checkNaming(array $tables): array
    {
        $fkViolations = [];

        foreach ($tables as $table) {
            if (\in_array($table->getName(), self::TABLES_WITHOUT_DEFINITION, true)) {
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

                $name = mb_substr($foreignKey->getName(), mb_strlen($foreignKey->getNamespaceName()) + 1);

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

    private function findEntityNotices(string $struct, EntityDefinition $definition): array
    {
        $reflection = new \ReflectionClass($struct);

        $fields = $definition->getFields();

        $notices = [];
        foreach ($reflection->getProperties() as $property) {
            $key = $definition->getEntityName() . '.' . $property->getName();
            if (\in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($reflection->getParentClass()->getName() === MappingEntityDefinition::class) {
                continue;
            }

            if ($property->getDocComment() && mb_strpos($property->getDocComment(), '@internal') !== false) {
                continue;
            }

            if (!$fields->get($property->getName()) && !\in_array($property->getName(), self::IGNORED_ENTITY_PROPERTIES, true)) {
                $notices[] = sprintf('Field %s in entity struct is missing in %s', $property->getName(), $definition->getClass());
            }
        }

        return $notices;
    }

    private function validateStruct(string $struct, EntityDefinition $definition): array
    {
        $reflection = new \ReflectionClass($struct);

        $fields = $definition->getFields();

        $properties = [];
        $functionViolations = [];

        foreach ($fields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                continue;
            }

            $key = $definition->getEntityName() . '.' . $field->getPropertyName();
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
                $getterMethods[] = 'has' . ucfirst((string) preg_replace('/^has/', '', $propertyName));
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

            if (!$field->is(Runtime::class) && !$reflection->hasMethod($setter)) {
                $functionViolations[] = sprintf('No setter function for property %s in %s', $propertyName, $struct);
            }
        }

        return array_merge($properties, $functionViolations);
    }

    private function validateAssociations(EntityDefinition $definition): array
    {
        $violations = [];

        $associations = $definition->getFields()->filterInstance(AssociationField::class);

        if ($definition instanceof MappingEntityDefinition) {
            return [];
        }

        /** @var AssociationField $association */
        foreach ($associations as $association) {
            $key = $definition->getEntityName() . '.' . $association->getPropertyName();

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
                        $this->validateTranslationAssociation($definition, $association->getReferenceDefinition())
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

    private function validateEntityTranslationGettersAreNullable(EntityTranslationDefinition $translationDefinition): array
    {
        $violations = [];

        $classReflection = new \ReflectionClass($translationDefinition->getEntityClass());
        $reflectionMethods = $classReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        if ($classReflection->getName() === ArrayEntity::class) {
            $violations[$translationDefinition->getClass()][] = sprintf('No EntityClass defined for TranslationDefinition `%s`. Add Method: public function getEntityClass(): string', $translationDefinition->getClass());

            return $violations;
        }

        foreach ($reflectionMethods as $method) {
            if (mb_strpos($method->getName(), 'get') !== 0
                || $method->getDeclaringClass()->getName() !== $translationDefinition->getEntityClass()
                || mb_strpos($method->getName(), 'Id') === mb_strlen($method->getName()) - 2
            ) {
                continue;
            }

            // Is not a getter
            if ($method->getName() === 'getApiAlias') {
                continue;
            }

            if (!$method->hasReturnType()) {
                $violations[$translationDefinition->getClass()][] = sprintf('No return type is declared in `%s` for method `%s`', $translationDefinition->getClass(), $method->getName());

                continue;
            }

            /** @var \ReflectionNamedType $returnType */
            $returnType = $method->getReturnType();

            if ($returnType->getName() === $translationDefinition->getParentDefinition()->getEntityClass()) {
                continue;
            }

            if (!$returnType->allowsNull()) {
                $violations[$translationDefinition->getClass()][] = sprintf('The return type of `%s` is not nullable. All getter functions of EntityTranslationDefinitions need to be nullable!', $method->getName());
            }
        }

        return $violations;
    }

    private function validateEntityTranslationDefinitions(EntityTranslationDefinition $translationDefinition): array
    {
        $violations = [];

        $parentDefinition = $translationDefinition->getParentDefinition();
        $translationsAssociationFields = $parentDefinition->getFields()
            ->filterInstance(TranslationsAssociationField::class)
            ->filter(function (TranslationsAssociationField $f) use ($translationDefinition) {
                return $f->getReferenceDefinition() === $translationDefinition;
            })->getElements();

        if (empty($translationsAssociationFields)) {
            $violations[$parentDefinition->getClass()] = sprintf('The parentDefinition `%s` for `%s` should define a `TranslationsAssociationField for `%s`. The parentDefinition could be wrong too.', $parentDefinition->getClass(), $translationDefinition->getClass(), $translationDefinition->getClass());
        }

        return $violations;
    }

    private function validateTranslationAssociation(EntityDefinition $parentDefinition, EntityDefinition $translationDefinition): array
    {
        $translatedFieldsInParent = array_keys($parentDefinition->getFields()->filterInstance(TranslatedField::class)->getElements());

        $translatedFields = array_keys($translationDefinition->getFields()->filter(function (Field $f) {
            return !$f->is(PrimaryKey::class)
                && !$f instanceof AssociationField
                && !\in_array($f->getPropertyName(), ['createdAt', 'updatedAt'], true);
        })->getElements());

        $violations = [];

        $onlyParent = array_diff($translatedFieldsInParent, $translatedFields);
        foreach ($onlyParent as $propertyName) {
            $violations[$translationDefinition->getClass()] = sprintf('Field `%s` defined in `%s`, but missing in `%s`', $propertyName, $parentDefinition->getClass(), $translationDefinition->getClass());
        }

        $onlyTranslated = array_diff($translatedFields, $translatedFieldsInParent);
        foreach ($onlyTranslated as $propertyName) {
            $violations[$parentDefinition->getClass()] = sprintf('Field `%s` defined in `%s`, but missing in `%s`. Please add `new TranslatedField(\'%s\') to `%s`', $propertyName, $translationDefinition->getClass(), $parentDefinition->getClass(), $propertyName, $parentDefinition->getClass());
        }

        return $violations;
    }

    private function validateOneToOne(EntityDefinition $definition, OneToOneAssociationField $association): array
    {
        $reference = $association->getReferenceDefinition();

        $associationViolations = [];

        /** @var OneToOneAssociationField|null $reverseSide */
        $reverseSide = $reference->getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof OneToOneAssociationField) {
                    return false;
                }
                $reference = $field->getReferenceDefinition();

                return $field->getStorageName() === $association->getReferenceField() && $reference === $definition;
            }
        )->first();

        if ($reverseSide === null) {
            $associationViolations[$definition->getClass()][] = sprintf(
                'Missing reverse one to one association for %s <-> %s (%s)',
                $definition->getClass(),
                $association->getReferenceDefinition()->getClass(),
                $association->getPropertyName()
            );

            return $associationViolations;
        }

        if ($association->is(CascadeDelete::class) && $reverseSide->is(CascadeDelete::class)) {
            $associationViolations[$definition->getClass()][] = sprintf(
                'Remove cascade delete in definition %s association: %s. One to One association should only have one side defined cascade delete flag',
                $definition->getClass(),
                $association->getPropertyName()
            );
        }

        if ($association->getAutoload() && $reverseSide->getAutoload()) {
            $associationViolations[$definition->getClass()][] = sprintf(
                'Remove autoload flag in definition %s association: %s. One to One association should only have one side defined as autoload, otherwise it leads to endless loops inside the DAL.',
                $definition->getClass(),
                $association->getPropertyName()
            );
        }

        $versionError = $this->validateVersionAwareness($reference, $definition, $association);
        if ($versionError) {
            $associationViolations[$definition->getClass()][] = $versionError;
        }

        return $associationViolations;
    }

    private function validateManyToOne(EntityDefinition $definition, ManyToOneAssociationField $association): array
    {
        $reference = $association->getReferenceDefinition();

        $associationViolations = [];

        /** @var OneToManyAssociationField|null $reverseSide */
        $reverseSide = $reference->getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof OneToManyAssociationField) {
                    return false;
                }
                $reference = $field->getReferenceDefinition();

                return $field->getLocalField() === $association->getReferenceField() && $reference === $definition;
            }
        )->first();

        if ($reverseSide === null) {
            $associationViolations[$definition->getClass()][] = sprintf(
                'Missing reverse one to many association for %s <-> %s (%s)',
                $definition->getClass(),
                $association->getReferenceDefinition()->getClass(),
                $association->getPropertyName()
            );
        }

        if ($association->getFlag(CascadeDelete::class)) {
            $associationViolations[$definition->getClass()][] = sprintf(
                'Remove cascade delete in definition %s association: %s. Many to one association should not have a cascade delete',
                $definition->getClass(),
                $association->getPropertyName()
            );
        }

        $versionError = $this->validateVersionAwareness($reference, $definition, $association);
        if ($versionError) {
            $associationViolations[$definition->getClass()][] = $versionError;
        }

        return $associationViolations;
    }

    private function validateOneToMany(EntityDefinition $definition, OneToManyAssociationField $association): array
    {
        $reference = $association->getReferenceDefinition();

        $associationViolations = $this->validateIsPlural($definition, $association);
        $associationViolations = $this->validateSetterIsNotNull($definition, $association, $associationViolations);

        $reference->getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof ManyToOneAssociationField) {
                    return false;
                }

                return $field->getStorageName() === $association->getReferenceField() && $field->getReferenceDefinition() === $definition;
            }
        )->first();

        $foreignKey = $reference->getFields()->getByStorageName($association->getReferenceField());

        if (!$foreignKey instanceof FkField) {
            $isGeneric = \in_array(
                $reference->getEntityName() . '.' . $foreignKey->getPropertyName(),
                self::GENERIC_FK_FIELDS,
                true
            );

            if (!$isGeneric) {
                $associationViolations[$definition->getClass()][] = sprintf(
                    'Missing reference foreign key for column %s for definition association %s.%s',
                    $association->getReferenceField(),
                    $definition->getEntityName(),
                    $association->getPropertyName()
                );
            }
        }

        $associationViolations = $this->validateForeignKeyOnDeleteBehaviour($definition, $association, $reference, $associationViolations);

        return $associationViolations;
    }

    private function validateManyToMany(EntityDefinition $definition, ManyToManyAssociationField $association): array
    {
        $reference = $association->getToManyReferenceDefinition();

        $violations = $this->validateIsPlural($definition, $association);
        $violations = $this->validateSetterIsNotNull($definition, $association, $violations);

        $mapping = $association->getMappingDefinition();
        $column = $association->getMappingReferenceColumn();
        $fk = $mapping->getFields()->getByStorageName($column);

        if (!$fk) {
            $violations[$mapping->getClass()][] = sprintf('Missing field %s in definition %s', $column, $mapping->getClass());
        }
        if ($fk && !$fk->is(PrimaryKey::class)) {
            $violations[$mapping->getClass()][] = sprintf('Foreign key field %s in definition %s is not part of the primary key', $column, $mapping->getClass());
        }
        if ($fk && !$fk instanceof FkField) {
            $violations[$mapping->getClass()][] = sprintf('Field %s in definition %s has to be defined as FkField', $column, $mapping->getClass());
        }

        $column = $association->getMappingReferenceColumn();
        $fk = $mapping->getFields()->getByStorageName($column);

        if (!$fk) {
            $violations[$mapping->getClass()][] = sprintf('Missing field %s in definition %s', $column, $mapping->getClass());
        }
        if ($fk && !$fk->is(PrimaryKey::class)) {
            $violations[$mapping->getClass()][] = sprintf('Foreign key field %s in definition %s is not part of the primary key', $column, $mapping->getClass());
        }
        if ($fk && !$fk instanceof FkField) {
            $violations[$mapping->getClass()][] = sprintf('Field %s in definition %s has to be defined as FkField', $column, $mapping->getClass());
        }

        if ($fk instanceof FkField && $fk->getReferenceDefinition() !== $association->getToManyReferenceDefinition()) {
            $violations[$definition->getClass()][] = sprintf(
                'Reference column %s of field %s should map to definition %s',
                $fk->getPropertyName(),
                $association->getPropertyName(),
                $association->getToManyReferenceDefinition()->getClass()
            );
        }

        /** @var FkField $localColumn */
        $localColumn = $mapping->getFields()->getByStorageName($association->getMappingLocalColumn());
        if ($localColumn->getReferenceDefinition() !== $definition) {
            $violations[$definition->getClass()][] = sprintf(
                'Local column %s of field %s should map to definition %s',
                $localColumn->getPropertyName(),
                $association->getPropertyName(),
                $definition->getClass()
            );
        }

        if ($definition->isVersionAware() && $reference->isVersionAware()) {
            $versionField = $mapping->getFields()->filter(function (Field $field) use ($definition) {
                return $field instanceof ReferenceVersionField && $field->getVersionReferenceDefinition() === $definition;
            })->first();

            if (!$versionField) {
                $violations[$mapping->getClass()][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $definition->getClass(), $mapping->getClass());
            }

            $referenceVersionField = $mapping->getFields()->filter(function (Field $field) use ($reference) {
                return $field instanceof ReferenceVersionField && $field->getVersionReferenceDefinition() === $reference;
            })->first();

            if (!$referenceVersionField) {
                $violations[$mapping->getClass()][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $reference->getClass(), $mapping->getClass());
            }
        }

        $violations = $this->validateForeignKeyOnDeleteBehaviour($definition, $association, $reference, $violations);

        $reverse = $reference->getFields()->filter(function (Field $field) use ($definition, $association) {
            return $field instanceof ManyToManyAssociationField
                && $field->getToManyReferenceDefinition() === $definition
                && $field->getMappingDefinition() === $association->getMappingDefinition();
        })->first();

        if (!$reverse) {
            $violations[$reference->getClass()][] = sprintf('Missing reverse many to many association for original %s.%s', $definition->getClass(), $association->getPropertyName());
        }

        $versionError = $this->validateVersionAwareness($reference, $definition, $association);
        if ($versionError) {
            $violations[$definition->getClass()][] = $versionError;
        }

        return $violations;
    }

    private function validateSchema(EntityDefinition $definition): array
    {
        $manager = $this->connection->getSchemaManager();

        $columns = $manager->listTableColumns($definition->getEntityName());

        $violations = [];
        $mappedFieldNames = [];

        foreach ($columns as $column) {
            $field = $definition->getFields()->getByStorageName($column->getName());

            if ($field) {
                $mappedFieldNames[] = $field->getPropertyName();

                continue;
            }

            /** @var Field $association */
            $association = $definition->getFields()->get($column->getName());

            if ($association instanceof AssociationField && $association->is(Inherited::class)) {
                $mappedFieldNames[] = $association->getPropertyName();

                continue;
            }
        }

        foreach (array_diff($definition->getFields()->getKeys(), $mappedFieldNames) as $notMapped) {
            /** @var Field $field */
            $field = $definition->getFields()->get($notMapped);
            if (!$field instanceof StorageAware) {
                continue;
            }

            if ($field->getFlag(Runtime::class)) {
                continue;
            }

            $violations[] = sprintf(
                'Field %s has no configured column',
                $notMapped
            );
        }

        return [$definition->getClass() => $violations];
    }

    private function validateColumn(EntityDefinition $definition): array
    {
        $manager = $this->connection->getSchemaManager();
        $columns = $manager->listTableColumns($definition->getEntityName());

        $notices = [];

        foreach ($columns as $column) {
            $field = $definition->getFields()->getByStorageName($column->getName());

            if ($field) {
                continue;
            }

            /** @var Field $association */
            $association = $definition->getFields()->get($column->getName());

            if ($association instanceof AssociationField && $association->is(Inherited::class)) {
                continue;
            }

            $notices[] = sprintf(
                'Column %s has no configured field',
                $column->getName()
            );
        }

        return [$definition->getClass() => $notices];
    }

    private function validateIsPlural(EntityDefinition $definition, AssociationField $association): array
    {
        if (!$association instanceof ManyToManyAssociationField && !$association instanceof OneToManyAssociationField) {
            return [];
        }

        $propName = $association->getPropertyName();
        if (mb_substr($propName, -1) === 's' || \in_array($propName, self::PLURAL_EXCEPTIONS, true)) {
            return [];
        }

        $ref = $this->getShortClassName($this->registry->get($association->getReferenceDefinition()->getClass()));
        $def = $this->getShortClassName($definition);

        $ref = str_replace($def, '', $ref);
        $refPlural = Inflector::pluralize($ref);

        if (mb_stripos($propName, $refPlural) === mb_strlen($propName) - mb_strlen($refPlural)) {
            return [];
        }

        return [
            $definition->getClass() => [
                sprintf(
                    'Association %s.%s does not end with a \'s\'.',
                    $definition->getEntityName(),
                    $association->getPropertyName()
                ),
            ],
        ];
    }

    private function mapRefNameContainedName(string $ref): string
    {
        $normalized = mb_strtolower(Inflector::tableize($ref));
        if (!isset(self::CUSTOM_SHORT_NAMES[$normalized])) {
            return $ref;
        }

        return self::CUSTOM_SHORT_NAMES[$normalized];
    }

    private function validateReferenceNameContainedInName(EntityDefinition $definition, AssociationField $association): array
    {
        if ($definition === $association->getReferenceDefinition()) {
            return [];
        }
        $prop = $association->getPropertyName();

        if (\in_array(mb_strtolower($prop), self::INGNORED_IN_PREFIX_CHECK, true)) {
            return [];
        }

        $ref = $association instanceof ManyToManyAssociationField
            ? $association->getToManyReferenceDefinition()
            : $association->getReferenceDefinition();

        $ref = $this->getShortClassName($ref);
        $def = $this->getShortClassName($definition);

        $ref = str_replace($def, '', $ref);

        $namespace = $this->getAggregateNamespace($definition);
        if ($namespace !== $ref) {
            $ref = str_replace($namespace, '', $ref);
        }

        $ref = $this->mapRefNameContainedName($ref);
        $refPlural = Inflector::pluralize($ref);
        $refSalesChannelPart = str_replace('SalesChannel', '', $ref);
        $refSalesChannelPartPlural = Inflector::pluralize($refSalesChannelPart);

        if (
            mb_stripos($prop, $ref) === false && mb_stripos($prop, $refPlural) === false
            && mb_stripos($prop, $refSalesChannelPart) === false && mb_stripos($prop, $refSalesChannelPartPlural) === false
        ) {
            $ret = [
                $definition->getClass() => [
                    sprintf(
                        'Association %s.%s does not contain reference class name `%s` or `%s` or `%s` or `%s`',
                        $definition->getEntityName(),
                        $association->getPropertyName(),
                        $ref,
                        $refPlural,
                        $refSalesChannelPart,
                        $refSalesChannelPartPlural
                    ),
                ],
            ];

            return $ret;
        }

        return [];
    }

    private function validateDataFieldNotPrefixedByEntityName(EntityDefinition $definition): array
    {
        $violations = [];

        foreach ($definition->getFields() as $field) {
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

            if (\in_array($field->getPropertyName(), self::CUSTOM_PREFIXED_NAMED, true)) {
                continue;
            }

            // Skip fields where Entity class name is prefix of reference class name
            if ($field instanceof FkField) {
                $refClass = $field instanceof ReferenceVersionField
                    ? $field->getVersionReferenceDefinition()
                    : $field->getReferenceDefinition();

                $ref = $this->getShortClassName($refClass);
                $def = $this->getShortClassName($definition);

                if (mb_stripos($ref, $def) === 0) {
                    continue;
                }
            }

            $entityNamePrefix = $definition->getEntityName() . '_';
            if (mb_strpos($field->getStorageName(), $entityNamePrefix) === 0) {
                $violations[] = sprintf(
                    'Storage name `%s` is prefixed by entity name `%s`. Use storage name `%s` instead.',
                    $field->getStorageName(),
                    mb_substr($entityNamePrefix, 0, -1),
                    mb_substr($field->getStorageName(), mb_strlen($entityNamePrefix))
                );
            }

            $defPrefix = $this->getShortClassName($definition);
            if (mb_strpos($field->getPropertyName(), $defPrefix) === 0) {
                $violations[] = sprintf(
                    'Property name `%s` is prefixed by struct name `%s`. Use property name `%s` instead',
                    $field->getPropertyName(),
                    $defPrefix,
                    lcfirst(mb_substr($field->getPropertyName(), mb_strlen($defPrefix)))
                );
            }
        }

        return $violations;
    }

    private function getShortClassName(EntityDefinition $definition): string
    {
        return lcfirst(preg_replace('/.*\\\\([^\\\\]+)Definition/', '$1', $definition->getClass()));
    }

    private function getAggregateNamespace(EntityDefinition $definition): string
    {
        return lcfirst(preg_replace('/.*\\\\([^\\\\]+)\\\\Aggregate.*/', '$1', $definition->getClass()));
    }

    private function checkEntityNameConstant(EntityDefinition $definition): array
    {
        $violations = [];
        // Definition has cosntant ENTITY_NAME and is not empty
        if (!\defined($definition->getClass() . '::ENTITY_NAME') || \constant($definition->getClass() . '::ENTITY_NAME') === '') {
            $violations = array_merge_recursive(
                $violations,
                [$definition->getClass() => [sprintf('ENTITY_NAME constant Missing in %s', $definition->getClass())]]
            );
        }

        // GetEntityName returns same Value as ENTITY_NAME
        if (\constant($definition->getClass() . '::ENTITY_NAME') !== $definition->getEntityName()) {
            $violations = array_merge_recursive(
                $violations,
                [$definition->getClass() => [sprintf('ENTITY_NAME constant differs from getEntityName in %s', $definition->getClass())]]
            );
        }

        return $violations;
    }

    private function checkParentDefinition(EntityDefinition $definition): array
    {
        if ($definition->getParentDefinition()) {
            return [];
        }

        if ($definition instanceof MappingEntityDefinition) {
            return [];
        }

        if (mb_strpos($definition->getClass(), '\\Aggregate\\') === false) {
            return [];
        }

        return [sprintf('Missing parent definition in aggregate definition %s', $definition->getClass())];
    }

    /**
     * @param OneToManyAssociationField|ManyToManyAssociationField $association
     */
    private function validateForeignKeyOnDeleteBehaviour(EntityDefinition $definition, AssociationField $association, EntityDefinition $reference, array $associationViolations): array
    {
        $manager = $this->connection->getSchemaManager();

        if ($association->getFlag(CascadeDelete::class)
            || $association->getFlag(RestrictDelete::class)
            || $association->getFlag(SetNullOnDelete::class)) {
            $fks = $manager->listTableForeignKeys($reference->getEntityName());

            foreach ($fks as $fk) {
                if ($fk->getForeignTableName() !== $definition->getEntityName() || !\in_array($association->getReferenceField(), $fk->getLocalColumns(), true)) {
                    continue;
                }

                /** @var Flag $deleteFlag */
                $deleteFlag = $association->getFlag(CascadeDelete::class)
                    ?? $association->getFlag(RestrictDelete::class)
                    ?? $association->getFlag(SetNullOnDelete::class);

                if (\in_array($fk->onDelete(), self::DELETE_FLAG_TO_ACTION_MAPPING[\get_class($deleteFlag)], true)) {
                    continue;
                }

                $associationViolations[$definition->getClass()][] = sprintf(
                    'ForeignKey "%s" on entity "%s" has wrong OnDelete behaviour, behaviour should be "%s",'
                    . 'because Association "%s" on entity "%s" defined flag "%s", got "%s" instead.',
                    $fk->getName(),
                    $reference->getEntityName(),
                    self::DELETE_FLAG_TO_ACTION_MAPPING[\get_class($deleteFlag)][0],
                    $association->getPropertyName(),
                    $definition->getEntityName(),
                    \get_class($deleteFlag),
                    $fk->onDelete()
                );
            }
        }

        return $associationViolations;
    }

    private function validateSetterIsNotNull(EntityDefinition $definition, AssociationField $association, array $associationViolations): array
    {
        $setter = 'set' . ucfirst($association->getPropertyName());

        $classReflection = new \ReflectionClass($definition->getEntityClass());
        $reflectionMethods = $classReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($reflectionMethods as $reflectionMethod) {
            if (mb_strpos($reflectionMethod->getName(), $setter) !== 0) {
                continue;
            }

            $param = $reflectionMethod->getParameters()[0];

            if ($param->allowsNull()) {
                $associationViolations[$definition->getClass()][]
                    = sprintf('Setter "%s" of Entity "%s" is nullable, but shouldn\'t allow null as it is a toMany association.', $setter, $definition->getEntityClass());
            }
        }

        return $associationViolations;
    }

    private function validateVersionAwareness(EntityDefinition $reference, EntityDefinition $definition, AssociationField $association): ?string
    {
        if (!$reference->isVersionAware()) {
            return null;
        }

        // see if this is the owning side
        $owningSide = $definition->getFields()->filterInstance(FkField::class)->filter(function (FkField $field) use ($reference): bool {
            return $field->getReferenceDefinition() === $reference;
        });

        if ($owningSide->count() === 0) {
            return null;
        }
        $referenceVersionFieldForReference = $definition->getFields()->filterInstance(ReferenceVersionField::class)->filter(function (ReferenceVersionField $field) use ($association): bool {
            return $field->getVersionReferenceDefinition()->getClass() === $association->getReferenceDefinition()->getClass();
        });

        if (\count($referenceVersionFieldForReference) > 0) {
            return null;
        }

        return sprintf(
            'Missing version reference for foreign key column %s.%s for definition association %s.%s',
            $association->getReferenceDefinition()->getEntityName(),
            $association->getReferenceField(),
            $definition->getEntityName(),
            $association->getPropertyName()
        );
    }
}
