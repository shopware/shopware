<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldForeignKeyConstraintMissingException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\DataStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Builds the command queue for write operations.
 *
 * Contains recursive calls from extract->map->AssociationInterface->extract->map->....
 */
class WriteCommandExtractor
{
    private EntityWriteGatewayInterface $entityExistenceGateway;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    private array $fieldsForPrimaryKeyMapping = [];

    public function __construct(
        EntityWriteGatewayInterface $entityExistenceGateway,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->entityExistenceGateway = $entityExistenceGateway;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function normalize(EntityDefinition $definition, array $rawData, WriteParameterBag $parameters): array
    {
        foreach ($rawData as $i => $row) {
            $parameters->setPath('/' . $i);

            $row = $this->normalizeSingle($definition, $row, $parameters);

            $rawData[$i] = $row;
        }

        return $rawData;
    }

    public function normalizeSingle(EntityDefinition $definition, array $data, WriteParameterBag $parameters): array
    {
        $done = [];

        foreach ($definition->getPrimaryKeys() as $pkField) {
            $data = $pkField->getSerializer()->normalize($pkField, $data, $parameters);
            $done[$pkField->getPropertyName()] = true;
        }

        $normalizedTranslations = false;
        foreach ($data as $property => $_) {
            if (\array_key_exists($property, $done)) {
                continue;
            }

            $field = $definition->getFields()->get($property);
            if ($field === null || $field instanceof AssociationField) {
                continue;
            }

            if ($field instanceof TranslatedField) {
                $normalizedTranslations = true;
            }

            try {
                $data = $field->getSerializer()->normalize($field, $data, $parameters);
            } catch (WriteFieldException $e) {
                $parameters->getContext()->getExceptions()->add($e);
            }
            $done[$property] = true;
        }

        $translationsField = $definition->getFields()->get('translations');
        if ($translationsField instanceof TranslationsAssociationField) {
            $data = $this->normalizeTranslations($translationsField, $data, $parameters, $normalizedTranslations);
        }

        foreach ($data as $property => $value) {
            if (\array_key_exists($property, $done)) {
                continue;
            }

            if ($property === 'extensions') {
                foreach ($value as $extensionName => $_) {
                    $field = $definition->getFields()->get($extensionName);
                    if ($field === null) {
                        continue;
                    }

                    try {
                        $value = $field->getSerializer()->normalize($field, $value, $parameters);
                    } catch (WriteFieldException $e) {
                        $parameters->getContext()->getExceptions()->add($e);
                    }
                }
                $data[$property] = $value;

                continue;
            }

            $field = $definition->getFields()->get($property);
            if ($field instanceof ChildrenAssociationField) {
                continue;
            }
            if ($field === null || !$field instanceof AssociationField) {
                continue;
            }

            try {
                $data = $field->getSerializer()->normalize($field, $data, $parameters);
            } catch (WriteFieldException $e) {
                $parameters->getContext()->getExceptions()->add($e);
            }
        }

        $field = $parameters->getDefinition()->getFields()->getChildrenAssociationField();
        if ($field !== null) {
            try {
                $data = $field->getSerializer()->normalize($field, $data, $parameters);
            } catch (WriteFieldException $e) {
                $parameters->getContext()->getExceptions()->add($e);
            }
        }

        $pk = [];
        foreach ($definition->getPrimaryKeys() as $pkField) {
            $v = $data[$pkField->getPropertyName()] ?? null;
            if ($v === null) {
                $pk = null;

                break;
            }
            $pk[$pkField->getPropertyName()] = $v;
        }
        // could be incomplete
        if ($pk !== null) {
            $parameters->getPrimaryKeyBag()->add($definition, $pk);
        }

        return $data;
    }

    public function extract(array $rawData, WriteParameterBag $parameters): array
    {
        $definition = $parameters->getDefinition();

        $fields = $this->getFieldsInWriteOrder($definition);

        $pkData = $this->getPrimaryKey($rawData, $parameters);

        /** @var Field&StorageAware $pkField */
        foreach ($definition->getPrimaryKeys() as $pkField) {
            $parameters->getContext()->set($parameters->getDefinition()->getClass(), $pkField->getPropertyName(), Uuid::fromBytesToHex($pkData[$pkField->getStorageName()]));
        }

        if ($definition instanceof MappingEntityDefinition) {
            // gateway will execute always a replace into
            $existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);
        } else {
            $existence = $this->entityExistenceGateway->getExistence($definition, $pkData, $rawData, $parameters->getCommandQueue());
        }

        if (!$existence->exists()) {
            $defaults = $existence->isChild() ? $definition->getChildDefaults() : $definition->getDefaults();
            $rawData = $this->fillRawDataWithDefaults($definition, $parameters, $rawData, $defaults);
        }

        $mainFields = $this->getMainFields($fields);

        // without child association
        $data = $this->map($mainFields, $rawData, $existence, $parameters);

        $this->updateCommandQueue($definition, $parameters, $existence, $pkData, $data);

        $translation = $definition->getField('translations');
        if ($translation instanceof TranslationsAssociationField) {
            $this->map([$translation], $rawData, $existence, $parameters);
        }

        // call map with child associations only
        $children = array_filter($fields, static function (Field $field) {
            return $field instanceof ChildrenAssociationField;
        });

        if (\count($children) > 0) {
            $this->map($children, $rawData, $existence, $parameters);
        }

        return $pkData;
    }

    public function extractJsonUpdate($data, EntityExistence $existence, WriteParameterBag $parameters): void
    {
        foreach ($data as $storageName => $attributes) {
            $definition = $this->definitionRegistry->getByEntityName($existence->getEntityName());

            $pks = Uuid::fromHexToBytesList($existence->getPrimaryKey());
            $jsonUpdateCommand = new JsonUpdateCommand(
                $definition,
                $storageName,
                $attributes,
                $pks,
                $existence,
                $parameters->getPath()
            );
            $parameters->getCommandQueue()->add($jsonUpdateCommand->getDefinition(), $jsonUpdateCommand);
        }
    }

    private function normalizeTranslations(TranslationsAssociationField $translationsField, array $data, WriteParameterBag $parameters, bool $hasNormalizedTranslations): array
    {
        if (!$hasNormalizedTranslations) {
            $definition = $parameters->getDefinition();
            if (!$translationsField->is(Required::class)) {
                return $data;
            }

            $parentField = $this->getParentField($definition);
            if ($parentField && isset($data[$parentField->getPropertyName()])) {
                // only normalize required translations if it's not a child
                return $data;
            }
        }

        try {
            $data = $translationsField->getSerializer()->normalize($translationsField, $data, $parameters);
        } catch (WriteFieldException $e) {
            $parameters->getContext()->getExceptions()->add($e);
        }

        return $data;
    }

    private function getParentField(EntityDefinition $definition): ?FkField
    {
        if (!$definition->isInheritanceAware()) {
            return null;
        }

        /** @var ManyToOneAssociationField|null $parent */
        $parent = $definition->getFields()->get('parent');

        if (!$parent) {
            throw new ParentFieldNotFoundException($definition);
        }

        if (!$parent instanceof ManyToOneAssociationField) {
            throw new InvalidParentAssociationException($definition, $parent);
        }

        $fk = $definition->getFields()->getByStorageName($parent->getStorageName());

        if (!$fk) {
            throw new CanNotFindParentStorageFieldException($definition);
        }
        if (!$fk instanceof FkField) {
            throw new ParentFieldForeignKeyConstraintMissingException($definition, $fk);
        }

        return $fk;
    }

    private function map(array $fields, array $rawData, EntityExistence $existence, WriteParameterBag $parameters): array
    {
        $stack = new DataStack($rawData);

        foreach ($fields as $field) {
            $kvPair = $this->getKeyValuePair($field, $stack, $existence);
            if ($kvPair === null) {
                continue;
            }

            try {
                if ($field->is(WriteProtected::class)) {
                    $this->validateContextHasPermission($field, $kvPair, $parameters);
                }

                $values = $field->getSerializer()->encode($field, $existence, $kvPair, $parameters);

                foreach ($values as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (WriteFieldException $e) {
                $parameters->getContext()->getExceptions()->add($e);
            }
        }

        return $stack->getResultAsArray();
    }

    private function skipField(Field $field, EntityExistence $existence): bool
    {
        if ($existence->isChild() && $field->is(Inherited::class)) {
            //inherited field of a child is never required
            return true;
        }

        $create = !$existence->exists() || $existence->childChangedToParent();

        if (
            (!$field instanceof UpdatedAtField && !$field instanceof CreatedByField && !$field instanceof UpdatedByField)
            && (!$create || !$field->is(Required::class))
        ) {
            return true;
        }

        return false;
    }

    private function getKeyValuePair(Field $field, DataStack $stack, EntityExistence $existence): ?KeyValuePair
    {
        $kvPair = $stack->pop($field->getPropertyName());

        // not in data stack?
        if ($kvPair !== null) {
            return $kvPair;
        }

        if ($field instanceof ReferenceVersionField && $field->is(Required::class)) {
            return new KeyValuePair($field->getPropertyName(), null, true);
        }

        if ($this->skipField($field, $existence)) {
            return null;
        }

        return new KeyValuePair($field->getPropertyName(), null, true);
    }

    private function fillRawDataWithDefaults(EntityDefinition $definition, WriteParameterBag $parameters, array $rawData, array $defaults): array
    {
        if ($defaults === []) {
            return $rawData;
        }

        $toBeNormalized = $rawData;
        foreach ($defaults as $key => $value) {
            if (\array_key_exists($key, $rawData)) {
                continue;
            }

            $toBeNormalized[$key] = $value;
        }

        // clone write context so that the normalize of the default values does not affect the normal write
        $parameters = new WriteParameterBag($definition, clone $parameters->getContext(), $parameters->getPath(), $parameters->getCommandQueue(), $parameters->getPrimaryKeyBag());
        $normalized = $this->normalizeSingle($definition, $toBeNormalized, $parameters);

        foreach ($defaults as $key => $_) {
            if (\array_key_exists($key, $rawData)) {
                continue;
            }

            $rawData[$key] = $normalized[$key];
        }

        return $rawData;
    }

    private function updateCommandQueue(
        EntityDefinition $definition,
        WriteParameterBag $parameterBag,
        EntityExistence $existence,
        array $pkData,
        array $data
    ): void {
        $queue = $parameterBag->getCommandQueue();

        if ($existence->exists()) {
            $queue->add($definition, new UpdateCommand($definition, $data, $pkData, $existence, $parameterBag->getPath()));

            return;
        }

        $queue->add($definition, new InsertCommand($definition, array_merge($pkData, $data), $pkData, $existence, $parameterBag->getPath()));
    }

    /**
     * @return Field[]
     */
    private function getFieldsInWriteOrder(EntityDefinition $definition): array
    {
        $fields = $definition->getFields();

        $filtered = [];

        foreach ($fields as $field) {
            if ($field->is(Computed::class)) {
                continue;
            }

            $filtered[$field->getExtractPriority()][] = $field;
        }

        krsort($filtered, \SORT_NUMERIC);

        $sorted = [];
        foreach ($filtered as $fields) {
            foreach ($fields as $field) {
                $sorted[] = $field;
            }
        }

        return $sorted;
    }

    private function getPrimaryKey(array $rawData, WriteParameterBag $parameters): array
    {
        $pk = [];

        $pkFields = $parameters->getDefinition()->getPrimaryKeys();
        /** @var StorageAware&Field $pkField */
        foreach ($pkFields as $pkField) {
            $id = $rawData[$pkField->getPropertyName()] ?? null;

            $values = $pkField->getSerializer()->encode(
                $pkField,
                new EntityExistence($parameters->getDefinition()->getEntityName(), [], false, false, false, []),
                new KeyValuePair($pkField->getPropertyName(), $id, true),
                $parameters
            );
            foreach ($values as $key => $value) {
                $pk[$key] = $value;
            }
        }

        return $pk;
    }

    /**
     * Returns all fields which are relevant to extract and map the primary key data of an entity definition data array.
     * In case a primary key consist of Foreign Key fields, the corresponding association for these foreign keys must be
     * returned in order to guarantee the creation of these sub entities and to extract the corresponding foreign key value
     * from the nested data array
     *
     * Example: ProductCategoryDefinition
     * Primary key:   product_id, category_id
     *
     * Both fields are defined as foreign key field.
     * It is now possible to create both related entities (product and category), providing a nested data array:
     * [
     *      'product' => ['id' => '..', 'name' => '..'],
     *      'category' => ['id' => '..', 'name' => '..']
     * ]
     *
     * To extract the primary key data of the ProductCategoryDefinition it is required to extract first the product
     * and category association and their foreign key fields.
     *
     * @param Field[] $fields
     *
     * @return Field[]
     */
    private function getFieldsForPrimaryKeyMapping(array $fields, EntityDefinition $definition): array
    {
        if (isset($this->fieldsForPrimaryKeyMapping[$definition->getEntityName()])) {
            return $this->fieldsForPrimaryKeyMapping[$definition->getEntityName()];
        }

        $primaryKeys = $definition->getPrimaryKeys()->getElements();

        $references = array_filter($fields, static function (Field $field) {
            return $field instanceof ManyToOneAssociationField;
        });

        foreach ($primaryKeys as $primaryKey) {
            if (!$primaryKey instanceof FkField) {
                continue;
            }

            $association = $this->getAssociationByStorageName($primaryKey->getStorageName(), $references);
            if ($association) {
                $primaryKeys[] = $association;
            }
        }

        usort($primaryKeys, static function (Field $a, Field $b) {
            return $b->getExtractPriority() <=> $a->getExtractPriority();
        });

        return $this->fieldsForPrimaryKeyMapping[$definition->getEntityName()] = $primaryKeys;
    }

    private function getAssociationByStorageName(string $name, array $fields): ?ManyToOneAssociationField
    {
        /** @var ManyToOneAssociationField $association */
        foreach ($fields as $association) {
            if ($association->getStorageName() !== $name) {
                continue;
            }

            return $association;
        }

        return null;
    }

    /**
     * @param Field[] $fields
     *
     * @return Field[]
     */
    private function getMainFields(array $fields): array
    {
        $main = [];

        foreach ($fields as $field) {
            if ($field instanceof ChildrenAssociationField) {
                continue;
            }
            if ($field instanceof TranslationsAssociationField) {
                continue;
            }

            if ($field->is(Runtime::class)) {
                continue;
            }

            if (!$field->is(PrimaryKey::class)) {
                $main[] = $field;

                continue;
            }

            if ($field instanceof FkField) {
                $main[] = $field;
            }
        }

        return $main;
    }

    private function validateContextHasPermission(Field $field, KeyValuePair $data, WriteParameterBag $parameters): void
    {
        /** @var WriteProtected $flag */
        $flag = $field->getFlag(WriteProtected::class);

        if ($flag->isAllowed($parameters->getContext()->getContext()->getScope())) {
            return;
        }

        $message = 'This field is write-protected.';
        $allowedOrigins = '';
        if ($flag->getAllowedScopes()) {
            $message .= ' (Got: "%s" scope and "%s" is required)';
            $allowedOrigins = implode(' or ', $flag->getAllowedScopes());
        }

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                sprintf(
                    $message,
                    $parameters->getContext()->getContext()->getScope(),
                    $allowedOrigins
                ),
                $message,
                [
                    $parameters->getContext()->getContext()->getScope(),
                    $allowedOrigins,
                ],
                $data->getValue(),
                $data->getKey(),
                $data->getValue()
            )
        );

        $parameters->getContext()->getExceptions()->add(
            new WriteConstraintViolationException($violationList, $parameters->getPath() . '/' . $data->getKey())
        );
    }
}
