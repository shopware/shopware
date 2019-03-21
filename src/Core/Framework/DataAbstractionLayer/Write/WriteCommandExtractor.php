<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\DataStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InsufficientWritePermissionException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidJsonFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
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
     * @var FieldSerializerRegistry
     */
    private $fieldHandler;

    public function __construct(
        EntityWriteGatewayInterface $entityExistenceGateway,
        FieldSerializerRegistry $fieldHandler
    ) {
        $this->entityExistenceGateway = $entityExistenceGateway;
        $this->fieldHandler = $fieldHandler;
    }

    public function extract(array $rawData, WriteParameterBag $parameters): array
    {
        /* @var EntityDefinition|string $definition */
        $definition = $parameters->getDefinition();

        $fields = $this->getFieldsInWriteOrder($definition);

        $pkData = $this->getPrimaryKey($rawData, $parameters, $fields);

        $existence = $this->entityExistenceGateway->getExistence(
            $definition,
            $pkData,
            $rawData,
            $parameters->getCommandQueue()
        );

        $rawData = $this->integrateDefaults($definition, $rawData, $existence);

        $mainFields = $this->getMainFields($fields);

        // without child association
        $data = $this->map($mainFields, $rawData, $existence, $parameters);

        $this->updateCommandQueue($definition, $parameters->getCommandQueue(), $existence, $pkData, $data);

        // call map with child associations only
        $children = array_filter($fields, function (Field $field) {
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
            $jsonUpdateCommand = new JsonUpdateCommand(
                $existence->getDefinition(),
                $storageName,
                $existence->getPrimaryKey(),
                $attributes,
                $existence
            );
            $parameters->getCommandQueue()->add($jsonUpdateCommand->getDefinition(), $jsonUpdateCommand);
        }
    }

    private function map(array $fields, array $rawData, EntityExistence $existence, WriteParameterBag $parameters): array
    {
        $stack = new DataStack($rawData);

        foreach ($fields as $field) {
            try {
                $kvPair = $stack->pop($field->getPropertyName());
            } catch (ExceptionNoStackItemFound $e) {
                if ($field->is(Inherited::class) && $existence->isChild()) {
                    //inherited field of a child is never required
                    continue;
                }

                $create = !$existence->exists() || $existence->childChangedToParent();

                if (!$create && !$field instanceof UpdatedAtField) {
                    //update statement
                    continue;
                }

                if (!$field->is(Required::class)) {
                    //not required and childhood not changed
                    continue;
                }

                $kvPair = new KeyValuePair($field->getPropertyName(), null, true);
            }

            try {
                if ($field->is(WriteProtected::class)) {
                    $this->validateContextHasPermission($field, $kvPair, $parameters);
                }

                $values = $this->fieldHandler->encode($field, $existence, $kvPair, $parameters);

                foreach ($values as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (InvalidJsonFieldException $e) {
                foreach ($e->getExceptions() as $exception) {
                    $parameters->getExceptionStack()->add($exception);
                }
            } catch (WriteFieldException $e) {
                $parameters->getExceptionStack()->add($e);
            }
        }

        return $stack->getResultAsArray();
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function integrateDefaults(string $definition, array $rawData, EntityExistence $existence): array
    {
        $defaults = $definition::getDefaults($existence);

        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $rawData)) {
                continue;
            }

            $rawData[$key] = $value;
        }

        return $rawData;
    }

    private function updateCommandQueue(string $definition, WriteCommandQueue $queue, EntityExistence $existence, array $pkData, array $data): void
    {
        /* @var EntityDefinition $definition */
        if ($existence->exists()) {
            $queue->add($definition, new UpdateCommand($definition, $pkData, $data, $existence));

            return;
        }

        $queue->add($definition, new InsertCommand($definition, array_merge($pkData, $data), $pkData, $existence));
    }

    /**
     * @param EntityDefinition|string $definition
     *
     * @return Field[]
     */
    private function getFieldsInWriteOrder(string $definition): array
    {
        $fields = $definition::getFields();

        $filtered = [];

        /** @var Field $field */
        foreach ($fields as $field) {
            if ($field->is(Computed::class)) {
                continue;
            }

            $filtered[$field->getExtractPriority()][] = $field;
        }

        krsort($filtered, SORT_NUMERIC);

        $sorted = [];
        foreach ($filtered as $prio => $fields) {
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

        $existence = new EntityExistence($parameters->getDefinition(), [], false, false, false, []);

        //run data extraction for only this fields
        $mapped = $this->map($mappingFields, $rawData, $existence, $parameters);

        //after all fields extracted, filter fields to only primary key flaged fields
        $primaryKeys = array_filter($mappingFields, function (Field $field) {
            return $field->is(PrimaryKey::class);
        });

        $primaryKey = [];

        /** @var StorageAware|Field $field */
        foreach ($primaryKeys as $field) {
            //build new primary key data array which contains only the primary key data
            if (array_key_exists($field->getStorageName(), $mapped)) {
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
        $primaryKeys = array_filter($fields, function (Field $field) {
            return $field->is(PrimaryKey::class);
        });

        $references = array_filter($fields, function (Field $field) {
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

        usort($primaryKeys, function (Field $a, Field $b) {
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

            if ($field->is(Deferred::class)) {
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
            $message .= ' (Got: %s and %s is required)';
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

        throw new InsufficientWritePermissionException($parameters->getPath() . '/' . $data->getKey(), $violationList);
    }
}
