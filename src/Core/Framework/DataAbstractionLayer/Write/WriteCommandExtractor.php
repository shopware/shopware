<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\DataStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * Builds the command queue for write operations.
 *
 * Contains recursive calls from extract->map->AssociationInterface->extract->map->....
 */
#[Package('core')]
class WriteCommandExtractor
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityWriteGatewayInterface $entityExistenceGateway)
    {
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
    public function normalize(EntityDefinition $definition, array $rawData, WriteParameterBag $parameters): array
    {
        foreach ($rawData as $i => $row) {
            $parameters->setPath('/' . $i);

            $row = $this->normalizeSingle($definition, $row, $parameters);

            $rawData[$i] = $row;
        }

        return $rawData;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
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

    /**
     * @param array<string, mixed> $rawData
     *
     * @return string[]
     */
    public function extract(array $rawData, WriteParameterBag $parameters): array
    {
        $definition = $parameters->getDefinition();

        $fields = $this->getFieldsInWriteOrder($definition);

        $pkData = $this->getPrimaryKey($rawData, $parameters);

        /** @var Field&StorageAware $pkField */
        foreach ($definition->getPrimaryKeys() as $pkField) {
            $parameters->getContext()->set(
                $parameters->getDefinition()->getEntityName(),
                $pkField->getPropertyName(),
                $pkField->getSerializer()->decode($pkField, $pkData[$pkField->getStorageName()]),
            );
        }

        if ($definition instanceof MappingEntityDefinition) {
            // gateway will execute always a replace into
            $existence = EntityExistence::createForEntity($definition->getEntityName(), []);
        } else {
            $existence = $this->entityExistenceGateway->getExistence($definition, $pkData, $rawData, $parameters->getCommandQueue());
        }

        $stack = $this->createDataStack($existence, $definition, $parameters, $rawData);

        $mainFields = $this->getMainFields($fields);

        // without child association
        $data = $this->map($mainFields, $stack, $existence, $parameters);

        $this->updateCommandQueue($definition, $parameters, $existence, $pkData, $data);

        $translation = $definition->getField('translations');
        if ($translation instanceof TranslationsAssociationField) {
            $this->map([$translation], $stack, $existence, $parameters);
        }

        // call map with child associations only
        $children = array_filter($fields, static fn (Field $field) => $field instanceof ChildrenAssociationField);

        if (\count($children) > 0) {
            $this->map($children, $stack, $existence, $parameters);
        }

        return $pkData;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
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

    /**
     * @param array<Field> $fields
     *
     * @return array<string, mixed>
     */
    private function map(array $fields, DataStack $stack, EntityExistence $existence, WriteParameterBag $parameters): array
    {
        foreach ($fields as $field) {
            $kvPair = $this->getKeyValuePair($field, $stack, $existence);
            if ($kvPair === null) {
                continue;
            }

            try {
                if ($field->is(WriteProtected::class) && !$kvPair->isDefault()) {
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

    /**
     * @param array<string, mixed> $rawData
     */
    private function createDataStack(EntityExistence $existence, EntityDefinition $definition, WriteParameterBag $parameters, array $rawData): DataStack
    {
        if ($existence->exists()) {
            return new DataStack($rawData);
        }

        $defaults = $existence->isChild() ? $definition->getChildDefaults() : $definition->getDefaults();

        if ($defaults === []) {
            return new DataStack($rawData);
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

        $stack = new DataStack($rawData);
        foreach ($defaults as $key => $_) {
            if (\array_key_exists($key, $rawData)) {
                continue;
            }

            $stack->add($key, $normalized[$key], true);
        }

        return $stack;
    }

    /**
     * @param array<string, string> $pkData
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, string>
     */
    private function getPrimaryKey(array $rawData, WriteParameterBag $parameters): array
    {
        $pk = [];

        $pkFields = $parameters->getDefinition()->getPrimaryKeys();
        /** @var StorageAware&Field $pkField */
        foreach ($pkFields as $pkField) {
            $id = $rawData[$pkField->getPropertyName()] ?? null;

            /** @var array<string, string> $values */
            $values = $pkField->getSerializer()->encode(
                $pkField,
                EntityExistence::createForEntity($parameters->getDefinition()->getEntityName(), []),
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
