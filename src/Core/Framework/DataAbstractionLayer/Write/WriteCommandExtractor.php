<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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
    /**
     * @var EntityWriteGatewayInterface
     */
    private $entityExistenceGateway;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        EntityWriteGatewayInterface $entityExistenceGateway,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->entityExistenceGateway = $entityExistenceGateway;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function extract(array $rawData, WriteParameterBag $parameters): array
    {
        $definition = $parameters->getDefinition();

        $fields = $this->getFieldsInWriteOrder($definition);

        $pkData = $this->getPrimaryKey($rawData, $parameters, $fields);

        if ($definition instanceof MappingEntityDefinition) {
            // gateway will execute always a replace into
            $existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);
        } else {
            $existence = $this->entityExistenceGateway->getExistence($definition, $pkData, $rawData, $parameters->getCommandQueue());
        }

        if (!$existence->exists()) {
            if ($existence->isChild()) {
                $rawData = $this->integrateChildDefaults($definition, $rawData);
            } else {
                $rawData = $this->integrateDefaults($definition, $rawData);
            }
        }

        $mainFields = $this->getMainFields($fields);

        // without child association
        $data = $this->map($mainFields, $rawData, $existence, $parameters);

        $this->updateCommandQueue($definition, $parameters, $existence, $pkData, $data);

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

        if ($field->is(Inherited::class) && $existence->isChild()) {
            //inherited field of a child is never required
            return null;
        }

        $create = !$existence->exists() || $existence->childChangedToParent();

        if ($this->isUpdateAtFieldCase($create, $field)) {
            //update statement
            return null;
        }

        if ($this->isCreatedAtFieldCase($field)) {
            //not required and childhood not changed
            return null;
        }

        return new KeyValuePair($field->getPropertyName(), null, true);
    }

    private function isUpdateAtFieldCase(bool $create, Field $field): bool
    {
        if ($create) {
            return false;
        }
        if ($field instanceof UpdatedAtField) {
            return false;
        }
        if ($field instanceof CreatedByField) {
            return false;
        }
        if ($field instanceof UpdatedByField) {
            return false;
        }

        return true;
    }

    private function isCreatedAtFieldCase(Field $field): bool
    {
        if ($field->is(Required::class)) {
            return false;
        }
        if ($field instanceof UpdatedAtField) {
            return false;
        }
        if ($field instanceof CreatedByField) {
            return false;
        }
        if ($field instanceof UpdatedByField) {
            return false;
        }

        return true;
    }

    private function integrateDefaults(EntityDefinition $definition, array $rawData): array
    {
        $defaults = $definition->getDefaults();

        return $this->fillRawDataWithDefaults($rawData, $defaults);
    }

    private function integrateChildDefaults(EntityDefinition $definition, array $rawData): array
    {
        $defaults = $definition->getChildDefaults();

        return $this->fillRawDataWithDefaults($rawData, $defaults);
    }

    private function fillRawDataWithDefaults(array $rawData, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (\array_key_exists($key, $rawData)) {
                continue;
            }

            $rawData[$key] = $value;
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

        /* @var EntityDefinition $definition */
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

        /** @var Field $field */
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

    private function getPrimaryKey(array $rawData, WriteParameterBag $parameters, array $fields): array
    {
        //filter all fields which are relevant to extract the full primary key data
        //this function return additionally, to primary key flagged fields, foreign key fields and many to association
        $mappingFields = $this->getFieldsForPrimaryKeyMapping($fields);

        $existence = new EntityExistence($parameters->getDefinition()->getEntityName(), [], false, false, false, []);

        //run data extraction for only this fields
        $mapped = $this->map($mappingFields, $rawData, $existence, $parameters);

        //after all fields extracted, filter fields to only primary key flagged fields
        $primaryKeys = array_filter($mappingFields, static function (Field $field) {
            return $field->is(PrimaryKey::class);
        });

        $primaryKey = [];

        /** @var StorageAware|Field $field */
        foreach ($primaryKeys as $field) {
            //build new primary key data array which contains only the primary key data
            if (\array_key_exists($field->getStorageName(), $mapped)) {
                $primaryKey[$field->getStorageName()] = $mapped[$field->getStorageName()];
            }
        }

        return $primaryKey;
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
    private function getFieldsForPrimaryKeyMapping(array $fields): array
    {
        $primaryKeys = array_filter($fields, static function (Field $field) {
            return $field->is(PrimaryKey::class);
        });

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

        return $primaryKeys;
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
