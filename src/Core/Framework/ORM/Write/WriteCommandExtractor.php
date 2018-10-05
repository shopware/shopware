<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Write\Command\InsertCommand;
use Shopware\Core\Framework\ORM\Write\Command\UpdateCommand;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\ORM\Write\DataStack\DataStack;
use Shopware\Core\Framework\ORM\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\FieldAware\FieldExtenderCollection;
use Shopware\Core\Framework\ORM\Write\FieldAware\RuntimeExtender;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidJsonFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Deferred;
use Shopware\Core\Framework\ORM\Write\Flag\Inherited;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Locale\LocaleLanguageResolverInterface;

/**
 * Builds the command queue for write operations.
 *
 * Contains recursive calls from extract->map->SubResourceField->extract->map->....
 */
class WriteCommandExtractor
{
    /**
     * @var EntityWriteGatewayInterface
     */
    private $entityExistenceGateway;

    /**
     * @var LocaleLanguageResolverInterface
     */
    private $localeLanguageResolver;

    public function __construct(EntityWriteGatewayInterface $entityExistenceGateway, LocaleLanguageResolverInterface $localeLanguageResolver)
    {
        $this->entityExistenceGateway = $entityExistenceGateway;
        $this->localeLanguageResolver = $localeLanguageResolver;
    }

    public function extract(
        array $rawData,
        string $definition,
        FieldExceptionStack $exceptionStack,
        WriteCommandQueue $commandQueue,
        WriteContext $writeContext,
        FieldExtenderCollection $extender,
        string $path = ''
    ): array {
        $extender = clone $extender;
        $extender->addExtender(
            new RuntimeExtender(
                $definition,
                $writeContext,
                $commandQueue,
                $exceptionStack,
                $path,
                $this,
                $this->localeLanguageResolver
            )
        );

        /* @var EntityDefinition $definition */
        $commandQueue->updateOrder($definition, ...$definition::getWriteOrder());

        $fields = $this->getFieldsInWriteOrder($definition);

        $pkData = $this->getPrimaryKey($rawData, $definition, new FieldExceptionStack(), $extender, $fields);

        $existence = $this->entityExistenceGateway->getExistence($definition, $pkData, $rawData, $commandQueue);
        $rawData = $this->integrateDefaults($definition, $rawData, $existence);

        $mainFields = $this->getMainFields($fields);

        // without child association
        $data = $this->map($mainFields, $rawData, $existence, $exceptionStack, $extender);

        $this->updateCommandQueue($definition, $commandQueue, $existence, $pkData, $data);

        // call map with child associations only
        $children = array_filter($fields, function (Field $field) {
            return $field instanceof ChildrenAssociationField;
        });

        if (\count($children) > 0) {
            $this->map($children, $rawData, $existence, $exceptionStack, $extender);
        }

        return $pkData;
    }

    private function map(
        array $fields,
        array $rawData,
        EntityExistence $existence,
        FieldExceptionStack $exceptionStack,
        FieldExtenderCollection $extender
    ): array {
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

            $kvPair = $this->convertValue($field, $kvPair);

            $extender->extend($field);

            try {
                foreach ($field($existence, $kvPair) as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (InvalidJsonFieldException $e) {
                foreach ($e->getExceptions() as $exception) {
                    $exceptionStack->add($exception);
                }
            } catch (WriteFieldException $e) {
                $exceptionStack->add($e);
            }
        }

        return $stack->getResultAsArray();
    }

    private function integrateDefaults(string $definition, array $rawData, EntityExistence $existence): array
    {
        /** @var EntityDefinition $definition */
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

    private function convertValue(Field $field, KeyValuePair $kvPair): KeyValuePair
    {
        if ($field instanceof DateField && \is_string($kvPair->getValue())) {
            $kvPair = new KeyValuePair($kvPair->getKey(), new \DateTime($kvPair->getValue()), $kvPair->isRaw());
        }

        return $kvPair;
    }

    /**
     * @param EntityDefinition|string $definition
     *
     * @return Field[]
     */
    private function getFieldsInWriteOrder(string $definition): array
    {
        $fields = $definition::getFields()->getElements();

        $filtered = [];

        /** @var Field $field */
        foreach ($fields as $field) {
            if ($field->is(ReadOnly::class)) {
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

    /**
     * @param array                   $rawData
     * @param string|EntityDefinition $definition
     * @param FieldExceptionStack     $exceptionStack
     * @param FieldExtenderCollection $extender
     * @param Field[]                 $fields
     *
     * @return array
     */
    private function getPrimaryKey(
        array $rawData,
        string $definition,
        FieldExceptionStack $exceptionStack,
        FieldExtenderCollection $extender,
        array $fields
    ): array {
        //filter all fields which are relevant to extract the full primary key data
        //this function return additionally, to primary key flagged fields, foreign key fields and many to association
        $mappingFields = $this->getFieldsForPrimaryKeyMapping($fields);

        $existence = new EntityExistence($definition, [], false, false, false, []);

        //run data extraction for only this fields
        $mapped = $this->map($mappingFields, $rawData, $existence, $exceptionStack, $extender);

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
            return $field instanceof ReferenceField;
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
}
