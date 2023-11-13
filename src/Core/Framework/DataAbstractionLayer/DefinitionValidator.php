<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * @final
 */
#[Package('core')]
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
        'sales_channel.wishlists',
        'product.wishlists',
        'order.billingAddress',
        'product_search_config.excludedTerms',
        'integration.writeAccess',
        'media.metaDataRaw',
        'product.sortedProperties',
        'product.cheapestPriceContainer',
        'product.cheapest_price',
        'product.cheapest_price_accessor',

        // @deprecated tag:v6.6.0 - Deprecated columns
        'shipping_method_price.currency',
        'payment_method.shortName',
        'state_machine_history.entityId',
    ];

    private const PLURAL_EXCEPTIONS = [
        'children',
        'categoriesRo',
        'properties',
        'media',
        'productMedia',
        'mailTemplateMedia',
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
        'orderDateTime',
        'documentMediaFileId',
        'appSecret',
        'manufacturerId',
        'productManufacturerVersionId',
        'coverId',
        'productMediaVersionId',
        'featureSetId',
    ];

    private const TABLES_WITHOUT_DEFINITION = [
        'admin_elasticsearch_index_task',
        'app_config',
        'cart',
        'migration',
        'sales_channel_api_context',
        'elasticsearch_index_task',
        'increment',
        'messenger_messages',
        'payment_token',
        'refresh_token',
        'usage_data_entity_deletion',
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

    private const IGNORED_PARENT_DEFINITION = [
        // is a root definition, but is in aggregate namespace
        'customer_group',
        'sales_channel_type',
        'flow_template',
        'import_export_file',
        'import_export_log',
        'mail_header_footer',
        'mail_template_type',
        'product_search_config',
        'product_feature_set',
        'product_manufacturer',
        'product_keyword_dictionary',
        'media_thumbnail_size',
        'media_default_folder',
        'media_folder_configuration',
        'media_folder',
        'number_range_type',
        'newsletter_recipient',
        'tax_rule',
        'tax_rule_type',
        'snippet_set',
        'document_type',
        'app_payment_method',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        $violations = [];

        foreach ($this->registry->getDefinitions() as $definition) {
            $definitionClass = $definition->getClass();
            // ignore definitions from a test namespace
            if (preg_match('/.*\\\\Test|s\\\\.*/', $definitionClass) || preg_match('/.*ComposerChild\\\\.*/', $definitionClass)) {
                continue;
            }
            $violations[$definitionClass] = [];

            $violations = array_merge_recursive($violations, $this->validateSchema($definition));

            $violations = array_merge_recursive($violations, $this->validateColumn($definition));

            $violations = array_merge_recursive($violations, $this->checkEntityNameConstant($definition));

            $struct = ArrayEntity::class;
            if (!$definition instanceof MappingEntityDefinition) {
                $struct = $definition->getEntityClass();
            }

            if ($struct !== ArrayEntity::class) {
                $violations[$definitionClass] = array_merge(
                    $violations[$definitionClass],
                    $this->validateStruct($struct, $definition)
                );

                $violations[$definitionClass] = array_merge(
                    $violations[$definitionClass],
                    $this->findEntityNotices($struct, $definition)
                );
            }

            $notices[$definitionClass] = array_merge_recursive(
                $violations[$definitionClass],
                $this->validateDataFieldNotPrefixedByEntityName($definition)
            );

            $notices[$definitionClass] = array_merge_recursive(
                $violations[$definitionClass],
                $this->checkParentDefinition($definition)
            );

            $violations = array_merge_recursive($violations, $this->validateAssociations($definition));

            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                $violations = array_merge_recursive($violations, $this->validateTranslatedColumnsAreNullable($definition));
                $violations = array_merge_recursive($violations, $this->validateEntityTranslationGettersAreNullable($definition));
                $violations = array_merge_recursive($violations, $this->validateEntityTranslationDefinitions($definition));
            }

            $parentDefinition = $definition->getParentDefinition();
            if ($parentDefinition !== null) {
                $violations = array_merge_recursive($violations, $this->validateParentDefinitionAssociation($definition, $parentDefinition));
            }
        }

        $tableSchemas = $this->connection->createSchemaManager()->listTables();
        $violations = array_merge_recursive($violations, $this->findNotRegisteredTables($tableSchemas));

        return array_filter($violations);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNotices(): array
    {
        return [];
    }

    /**
     * @param Table[] $tables
     *
     * @return array<string, mixed>
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
            } catch (DefinitionNotFoundException) {
                $violations[] = sprintf(
                    'Table %s has no configured definition',
                    $table->getName()
                );
            }
        }

        return [DefinitionInstanceRegistry::class => $violations];
    }

    /**
     * @param class-string<Entity> $struct
     *
     * @return array<int, mixed>
     */
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

            $parentClass = $reflection->getParentClass();
            if (!$parentClass) {
                continue;
            }

            if ($parentClass->getName() === MappingEntityDefinition::class) {
                continue;
            }

            if ($property->getDocComment() && (str_contains($property->getDocComment(), '@internal') || str_contains($property->getDocComment(), '@deprecated'))) {
                continue;
            }

            if (!$fields->get($property->getName()) && !\in_array($property->getName(), self::IGNORED_ENTITY_PROPERTIES, true)) {
                $notices[] = sprintf('Field %s in entity struct is missing in %s', $property->getName(), $definition->getClass());
            }
        }

        return $notices;
    }

    /**
     * @param class-string<Entity> $struct
     *
     * @return array<int, mixed>
     */
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

        return [...$properties, ...$functionViolations];
    }

    /**
     * @return array<int, mixed>
     */
    private function validateAssociations(EntityDefinition $definition): array
    {
        $violations = [];

        $associations = $definition->getFields()->filterInstance(AssociationField::class);

        if ($definition instanceof MappingEntityDefinition) {
            return [];
        }

        foreach ($associations as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            $key = $definition->getEntityName() . '.' . $association->getPropertyName();

            if (\in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($association->is(Extension::class)) {
                continue;
            }

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

    /**
     * @return array<string, mixed>
     */
    private function validateTranslatedColumnsAreNullable(EntityTranslationDefinition $translationDefinition): array
    {
        $violations = [];

        $columns = $this->connection->createSchemaManager()->listTableColumns($translationDefinition->getEntityName());

        $translatedFields = $translationDefinition->getParentDefinition()
            ->getFields()
            ->filterInstance(TranslatedField::class);

        /** @var Field $translatedField */
        foreach ($translatedFields as $translatedField) {
            $translationField = $translationDefinition->getField($translatedField->getPropertyName());

            if ($translationField === null || !method_exists($translationField, 'getStorageName')) {
                continue;
            }

            /** @var string $storageName */
            $storageName = $translationField->getStorageName();

            $column = $this->getColumnByName($storageName, $columns);

            if ($column === null) {
                continue;
            }

            if ($column->getNotnull() && empty($column->getDefault())) {
                $violations[$translationDefinition->getClass()][] = sprintf(
                    'Column `%s`.`%s` is not nullable',
                    $translationDefinition->getEntityName(),
                    $storageName
                );
            }
        }

        return $violations;
    }

    /**
     * @param array<int, Column> $columns
     */
    private function getColumnByName(string $name, array $columns): ?Column
    {
        foreach ($columns as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntityTranslationGettersAreNullable(EntityTranslationDefinition $translationDefinition): array
    {
        $violations = [];

        $classReflection = new \ReflectionClass($translationDefinition->getEntityClass());
        $reflectionMethods = $classReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $translationDefinitionClass = $translationDefinition->getClass();
        if ($classReflection->getName() === ArrayEntity::class) {
            $violations[$translationDefinitionClass][] = sprintf('No EntityClass defined for TranslationDefinition `%s`. Add Method: public function getEntityClass(): string', $translationDefinitionClass);

            return $violations;
        }

        foreach ($reflectionMethods as $method) {
            if (!str_starts_with($method->getName(), 'get')
                || $method->getDeclaringClass()->getName() !== $translationDefinition->getEntityClass()
                || mb_strpos($method->getName(), 'Id') === mb_strlen($method->getName()) - 2
            ) {
                continue;
            }

            // Is not a getter
            if ($method->getName() === 'getApiAlias') {
                continue;
            }

            $translationDefinitionClass = $translationDefinition->getClass();
            if (!$method->hasReturnType()) {
                $violations[$translationDefinitionClass][] = sprintf('No return type is declared in `%s` for method `%s`', $translationDefinitionClass, $method->getName());

                continue;
            }

            $returnType = $method->getReturnType();

            if (!$returnType instanceof \ReflectionNamedType || $returnType->getName() === $translationDefinition->getParentDefinition()->getEntityClass()) {
                continue;
            }

            if (!$returnType->allowsNull() && !\in_array($method->getName(), ['getCustomFieldsValue', 'getCustomFieldsValues'], true)) {
                $violations[$translationDefinitionClass][] = sprintf('The return type of `%s` is not nullable. All getter functions of EntityTranslationDefinitions need to be nullable!', $method->getName());
            }
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntityTranslationDefinitions(EntityTranslationDefinition $translationDefinition): array
    {
        $violations = [];

        $parentDefinition = $translationDefinition->getParentDefinition();
        $translationsAssociationFields = $parentDefinition->getFields()
            ->filterInstance(TranslationsAssociationField::class)
            ->filter(fn (TranslationsAssociationField $f) => $f->getReferenceDefinition() === $translationDefinition)->getElements();

        $parentDefinitionClass = $parentDefinition->getClass();
        $translationDefinitionClass = $translationDefinition->getClass();
        if (empty($translationsAssociationFields)) {
            $violations[$parentDefinitionClass] = sprintf('The parentDefinition `%s` for `%s` should define a `TranslationsAssociationField for `%s`. The parentDefinition could be wrong too.', $parentDefinitionClass, $translationDefinitionClass, $translationDefinitionClass);
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTranslationAssociation(EntityDefinition $parentDefinition, EntityDefinition $translationDefinition): array
    {
        $translatedFieldsInParent = array_keys($parentDefinition->getFields()->filterInstance(TranslatedField::class)->getElements());

        $translatedFields = array_keys($translationDefinition->getFields()->filter(fn (Field $f) => !$f->is(PrimaryKey::class)
            && !$f instanceof AssociationField
            && !\in_array($f->getPropertyName(), ['createdAt', 'updatedAt'], true))->getElements());

        $violations = [];

        $parentDefinitionClass = $parentDefinition->getClass();
        $translationDefinitionClass = $translationDefinition->getClass();
        $onlyParent = array_diff($translatedFieldsInParent, $translatedFields);
        foreach ($onlyParent as $propertyName) {
            $violations[$translationDefinitionClass] = sprintf('Field `%s` defined in `%s`, but missing in `%s`', $propertyName, $parentDefinitionClass, $translationDefinitionClass);
        }

        $onlyTranslated = array_diff($translatedFields, $translatedFieldsInParent);
        foreach ($onlyTranslated as $propertyName) {
            $violations[$parentDefinitionClass] = sprintf('Field `%s` defined in `%s`, but missing in `%s`. Please add `new TranslatedField(\'%s\') to `%s`', $propertyName, $translationDefinitionClass, $parentDefinitionClass, $propertyName, $parentDefinitionClass);
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
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

        $definitionClass = $definition->getClass();
        if ($reverseSide === null) {
            $associationViolations[$definitionClass][] = sprintf(
                'Missing reverse one-to-one association for %s <-> %s (%s)',
                $definitionClass,
                $association->getReferenceDefinition()->getClass(),
                $association->getPropertyName()
            );

            return $associationViolations;
        }

        if ($association->is(CascadeDelete::class) && $reverseSide->is(CascadeDelete::class)) {
            $associationViolations[$definitionClass][] = sprintf(
                'Remove cascade delete in definition %s association: %s. One to One association should only have one side defined cascade delete flag',
                $definitionClass,
                $association->getPropertyName()
            );
        }

        if ($association->getAutoload() && $reverseSide->getAutoload()) {
            $associationViolations[$definitionClass][] = sprintf(
                'Remove autoload flag in definition %s association: %s. One-to-One association should only have one side defined as autoload, otherwise it leads to endless loops inside the DAL.',
                $definitionClass,
                $association->getPropertyName()
            );
        }

        $versionError = $this->validateVersionAwareness($reference, $definition, $association);
        if ($versionError) {
            $associationViolations[$definitionClass][] = $versionError;
        }

        return $associationViolations;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateManyToOne(EntityDefinition $definition, ManyToOneAssociationField $association): array
    {
        $reference = $association->getReferenceDefinition();

        $associationViolations = [];

        $reverseSide = $reference->getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof OneToManyAssociationField) {
                    return false;
                }
                $reference = $field->getReferenceDefinition();

                return $field->getLocalField() === $association->getReferenceField() && $reference === $definition;
            }
        )->first();

        $definitionClass = $definition->getClass();
        if ($reverseSide === null) {
            $associationViolations[$definitionClass][] = sprintf(
                'Missing reverse one-to-many association for %s <-> %s (%s)',
                $definitionClass,
                $association->getReferenceDefinition()->getClass(),
                $association->getPropertyName()
            );
        }

        if ($association->getFlag(CascadeDelete::class)) {
            $associationViolations[$definitionClass][] = sprintf(
                'Remove cascade delete in definition %s association: %s. Many to one association should not have a cascade delete',
                $definitionClass,
                $association->getPropertyName()
            );
        }

        $versionError = $this->validateVersionAwareness($reference, $definition, $association);
        if ($versionError) {
            $associationViolations[$definitionClass][] = $versionError;
        }

        return $associationViolations;
    }

    /**
     * @return array<int|string, mixed>
     */
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

        if ($foreignKey instanceof Field && !$foreignKey instanceof FkField) {
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

        return $this->validateForeignKeyOnDeleteBehaviour($definition, $association, $reference, $associationViolations);
    }

    /**
     * @return array<int|string, mixed>
     */
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

        $definitionClass = $definition->getClass();
        if ($fk instanceof FkField && $fk->getReferenceDefinition() !== $association->getToManyReferenceDefinition()) {
            $violations[$definitionClass][] = sprintf(
                'Reference column %s of field %s should map to definition %s',
                $fk->getPropertyName(),
                $association->getPropertyName(),
                $association->getToManyReferenceDefinition()->getClass()
            );
        }

        $localColumn = $mapping->getFields()->getByStorageName($association->getMappingLocalColumn());
        if ($localColumn instanceof FkField && $localColumn->getReferenceDefinition() !== $definition) {
            $violations[$definitionClass][] = sprintf(
                'Local column %s of field %s should map to definition %s',
                $localColumn->getPropertyName(),
                $association->getPropertyName(),
                $definitionClass
            );
        }

        if ($definition->isVersionAware() && $reference->isVersionAware()) {
            $versionField = $mapping->getFields()->filter(fn (Field $field) => $field instanceof ReferenceVersionField && $field->getVersionReferenceDefinition() === $definition)->first();

            if (!$versionField) {
                $violations[$mapping->getClass()][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $definitionClass, $mapping->getClass());
            }

            $referenceVersionField = $mapping->getFields()->filter(fn (Field $field) => $field instanceof ReferenceVersionField && $field->getVersionReferenceDefinition() === $reference)->first();

            if (!$referenceVersionField) {
                $violations[$mapping->getClass()][] = sprintf('Missing reference version field for definition %s in mapping definition %s', $reference->getClass(), $mapping->getClass());
            }
        }

        $violations = $this->validateForeignKeyOnDeleteBehaviour($definition, $association, $reference, $violations);

        $reverse = $reference->getFields()->filter(fn (Field $field) => $field instanceof ManyToManyAssociationField
            && $field->getToManyReferenceDefinition() === $definition
            && $field->getMappingDefinition() === $association->getMappingDefinition())->first();

        if (!$reverse) {
            $violations[$reference->getClass()][] = sprintf('Missing reverse many-to-many association for original %s.%s', $definitionClass, $association->getPropertyName());
        }

        $versionError = $this->validateVersionAwareness($reference, $definition, $association);
        if ($versionError) {
            $violations[$definitionClass][] = $versionError;
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSchema(EntityDefinition $definition): array
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns($definition->getEntityName());

        $violations = [];
        $mappedFieldNames = [];

        foreach ($columns as $column) {
            $field = $definition->getFields()->getByStorageName($column->getName());

            if ($field) {
                $mappedFieldNames[] = $field->getPropertyName();

                continue;
            }

            $association = $definition->getFields()->get($column->getName());

            if ($association instanceof AssociationField && $association->is(Inherited::class)) {
                $mappedFieldNames[] = $association->getPropertyName();
            }
        }

        foreach (array_diff($definition->getFields()->getKeys(), $mappedFieldNames) as $notMapped) {
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

    /**
     * @return array<string, mixed>
     */
    private function validateColumn(EntityDefinition $definition): array
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns($definition->getEntityName());

        $notices = [];

        foreach ($columns as $column) {
            if (\in_array($definition->getEntityName() . '.' . $column->getName(), self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($definition->getFields()->getByStorageName($column->getName())) {
                continue;
            }

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

    /**
     * @return array<int|string, mixed>
     */
    private function validateIsPlural(EntityDefinition $definition, AssociationField $association): array
    {
        if (!$association instanceof ManyToManyAssociationField && !$association instanceof OneToManyAssociationField) {
            return [];
        }

        $propName = $association->getPropertyName();
        if (str_ends_with($propName, 's') || \in_array($propName, self::PLURAL_EXCEPTIONS, true)) {
            return [];
        }

        $ref = $this->getShortClassName($this->registry->get($association->getReferenceDefinition()->getClass()));
        $def = $this->getShortClassName($definition);

        $ref = str_replace($def, '', $ref);
        $refPlural = (new EnglishInflector())->pluralize($ref)[0];

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

    /**
     * @return array<int, mixed>
     */
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
                || $field instanceof OneToManyAssociationField
            ) {
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
            if (str_starts_with($field->getStorageName(), $entityNamePrefix)) {
                $violations[] = sprintf(
                    'Storage name `%s` is prefixed by entity name `%s`. Use storage name `%s` instead. ' . $field->getPropertyName(),
                    $field->getStorageName(),
                    mb_substr($entityNamePrefix, 0, -1),
                    mb_substr($field->getStorageName(), mb_strlen($entityNamePrefix))
                );
            }

            $defPrefix = $this->getShortClassName($definition);
            if (str_starts_with($field->getPropertyName(), $defPrefix) && $field->getPropertyName() !== $defPrefix) {
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
        return lcfirst((string) preg_replace('/.*\\\\([^\\\\]+)Definition/', '$1', $definition->getClass()));
    }

    /**
     * @return array<int, mixed>
     */
    private function checkEntityNameConstant(EntityDefinition $definition): array
    {
        $violations = [];
        $definitionClass = $definition->getClass();
        // Definition has constant ENTITY_NAME and is not empty
        if (!\defined($definitionClass . '::ENTITY_NAME') || \constant($definitionClass . '::ENTITY_NAME') === '') {
            $violations = array_merge_recursive(
                $violations,
                [$definitionClass => [sprintf('ENTITY_NAME constant Missing in %s', $definitionClass)]]
            );
        }

        // GetEntityName returns same Value as ENTITY_NAME
        if (\constant($definitionClass . '::ENTITY_NAME') !== $definition->getEntityName()) {
            $violations = array_merge_recursive(
                $violations,
                [$definitionClass => [sprintf('ENTITY_NAME constant differs from getEntityName in %s', $definitionClass)]]
            );
        }

        return $violations;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function checkParentDefinition(EntityDefinition $definition): array
    {
        if ($definition->getParentDefinition()) {
            return [];
        }

        if ($definition instanceof MappingEntityDefinition) {
            return [];
        }

        if (\in_array($definition->getEntityName(), self::IGNORED_PARENT_DEFINITION, true)) {
            return [];
        }

        $definitionClass = $definition->getClass();
        if (!str_contains($definitionClass, '\\Aggregate\\')) {
            return [];
        }

        return [sprintf('Missing parent definition in aggregate definition %s', $definitionClass)];
    }

    /**
     * @param array<int|string, mixed> $associationViolations
     *
     * @return array<int|string, mixed>
     */
    private function validateForeignKeyOnDeleteBehaviour(EntityDefinition $definition, OneToManyAssociationField|ManyToManyAssociationField $association, EntityDefinition $reference, array $associationViolations): array
    {
        $manager = $this->connection->createSchemaManager();

        if ($association->getFlag(CascadeDelete::class)
            || $association->getFlag(RestrictDelete::class)
            || $association->getFlag(SetNullOnDelete::class)) {
            $fks = $manager->listTableForeignKeys($reference->getEntityName());

            foreach ($fks as $fk) {
                if ($fk->getForeignTableName() !== $definition->getEntityName() || !\in_array($association->getReferenceField(), $fk->getLocalColumns(), true)) {
                    continue;
                }

                $deleteFlag = $association->getFlag(CascadeDelete::class)
                    ?? $association->getFlag(RestrictDelete::class)
                    ?? $association->getFlag(SetNullOnDelete::class);

                if (!$deleteFlag instanceof Flag) {
                    continue;
                }

                if (\in_array($fk->onDelete(), self::DELETE_FLAG_TO_ACTION_MAPPING[$deleteFlag::class], true)) {
                    continue;
                }

                $associationViolations[$definition->getClass()][] = sprintf(
                    'ForeignKey "%s" on entity "%s" has wrong OnDelete behaviour, behaviour should be "%s",'
                    . 'because Association "%s" on entity "%s" defined flag "%s", got "%s" instead.',
                    $fk->getName(),
                    $reference->getEntityName(),
                    self::DELETE_FLAG_TO_ACTION_MAPPING[$deleteFlag::class][0],
                    $association->getPropertyName(),
                    $definition->getEntityName(),
                    $deleteFlag::class,
                    $fk->onDelete()
                );
            }
        }

        return $associationViolations;
    }

    /**
     * @param array<int|string, mixed> $associationViolations
     *
     * @return array<int|string, mixed>
     */
    private function validateSetterIsNotNull(EntityDefinition $definition, AssociationField $association, array $associationViolations): array
    {
        $setter = 'set' . ucfirst($association->getPropertyName());

        $reflectionMethods = (new \ReflectionClass($definition->getEntityClass()))->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($reflectionMethods as $reflectionMethod) {
            if (!str_starts_with($reflectionMethod->getName(), $setter)) {
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
        $owningSide = $definition->getFields()->filterInstance(FkField::class)->filter(fn (FkField $field): bool => $field->getReferenceDefinition() === $reference);

        if ($owningSide->count() === 0) {
            return null;
        }
        $referenceVersionFieldForReference = $definition->getFields()->filterInstance(ReferenceVersionField::class)->filter(fn (ReferenceVersionField $field): bool => $field->getVersionReferenceDefinition()->getClass() === $association->getReferenceDefinition()->getClass());

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

    /**
     * @return array<class-string<EntityDefinition>, list<string>>
     */
    private function validateParentDefinitionAssociation(EntityDefinition $definition, EntityDefinition $parentDefinition): array
    {
        /** @var FkField $fkField */
        foreach ($definition->getFields()->filterInstance(FkField::class) as $fkField) {
            if ($fkField->getReferenceDefinition() === $parentDefinition) {
                return [];
            }
        }

        return [
            $definition->getClass() => [sprintf(
                'Entity "%s" defines parent entity "%s", but does not have a FK to that parent entity configured.',
                $definition->getEntityName(),
                $parentDefinition->getEntityName(),
            )],
        ];
    }
}
