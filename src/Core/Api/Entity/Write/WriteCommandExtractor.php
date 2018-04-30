<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Api\Entity\Write;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\ChildrenAssociationField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Command\InsertCommand;
use Shopware\Api\Entity\Write\Command\UpdateCommand;
use Shopware\Api\Entity\Write\Command\WriteCommandQueue;
use Shopware\Api\Entity\Write\DataStack\DataStack;
use Shopware\Api\Entity\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Entity\Write\FieldAware\RuntimeExtender;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Entity\Write\FieldException\WriteFieldException;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Entity\Write\Flag\Required;

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

    public function __construct(EntityWriteGatewayInterface $entityExistenceGateway)
    {
        $this->entityExistenceGateway = $entityExistenceGateway;
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
            new RuntimeExtender($definition, $writeContext, $commandQueue, $exceptionStack, $path, $this)
        );

        /* @var EntityDefinition $definition */
        $commandQueue->updateOrder($definition, ...$definition::getWriteOrder());

        $fields = $this->getFieldsInWriteOrder($definition);

        $pkData = $this->getPrimaryKey($rawData, $definition, new FieldExceptionStack(), $extender, $fields);

        $existence = $this->entityExistenceGateway->getExistence($definition, $pkData, $rawData, $commandQueue);
        $rawData = $this->integrateDefaults($definition, $rawData, $existence);

        $mainFields = $fields->filter(function (Field $field) {
            if ($field instanceof ChildrenAssociationField) {
                return false;
            }

            if (!$field->is(PrimaryKey::class)) {
                return true;
            }

            return $field instanceof FkField;
        });

        // without child association
        $data = $this->map($mainFields, $rawData, $existence, $exceptionStack, $extender);

        $this->updateCommandQueue($definition, $commandQueue, $existence, $pkData, $data);

        // call map with child associations only
        $children = $fields->filterInstance(ChildrenAssociationField::class);
        if ($children->count() > 0) {
            $this->map($children, $rawData, $existence, $exceptionStack, $extender);
        }

        return $pkData;
    }

    private function map(
        FieldCollection $fields,
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

                if (!$create) {
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
            $queue->add($definition, new UpdateCommand($definition, $pkData, $data));

            return;
        }

        $queue->add($definition, new InsertCommand($definition, array_merge($pkData, $data), $pkData));
    }

    private function convertValue(Field $field, KeyValuePair $kvPair): KeyValuePair
    {
        if ($field instanceof DateField && is_string($kvPair->getValue())) {
            $kvPair = new KeyValuePair($kvPair->getKey(), new \DateTime($kvPair->getValue()), $kvPair->isRaw());
        }

        return $kvPair;
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return FieldCollection
     */
    private function getFieldsInWriteOrder(string $definition): FieldCollection
    {
        $fields = clone $definition::getFields();
        $fields = $fields->filter(function (Field $field) {
            return !$field->is(ReadOnly::class);
        });

        $fields->sort(function (Field $a, Field $b) {
            return $b->getExtractPriority() <=> $a->getExtractPriority();
        });

        return $fields;
    }

    /**
     * @param array                   $rawData
     * @param string|EntityDefinition $definition
     * @param FieldExceptionStack     $exceptionStack
     * @param FieldExtenderCollection $extender
     * @param FieldCollection         $fields
     *
     * @return array
     */
    private function getPrimaryKey(
        array $rawData,
        string $definition,
        FieldExceptionStack $exceptionStack,
        FieldExtenderCollection $extender,
        FieldCollection $fields
    ): array {
        //filter all fields which are relevant to extract the full primary key data
        //this function return additionally, to primary key flagged fields, foreign key fields and many to association
        $mappingFields = $this->getFieldsForPrimaryKeyMapping($fields);

        $existence = new EntityExistence($definition, [], false, false, false);

        //run data extraction for only this fields
        $mapped = $this->map($mappingFields, $rawData, $existence, $exceptionStack, $extender);

        //after all fields extracted, filter fields to only primary key flaged fields
        $primaryKeys = $mappingFields->filterByFlag(PrimaryKey::class);

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
     * @param FieldCollection $fields
     *
     * @return FieldCollection
     */
    private function getFieldsForPrimaryKeyMapping(FieldCollection $fields): FieldCollection
    {
        $primaryKeys = $fields->filterByFlag(PrimaryKey::class);

        $references = $fields->filterInstance(ReferenceField::class);

        foreach ($primaryKeys as $primaryKey) {
            if (!$primaryKey instanceof FkField) {
                continue;
            }

            $association = $this->getAssociationByStorageName($primaryKey->getStorageName(), $references);
            if ($association) {
                $primaryKeys->add($association);
            }
        }

        $primaryKeys->sort(function (Field $a, Field $b) {
            return $b->getExtractPriority() <=> $a->getExtractPriority();
        });

        return $primaryKeys;
    }

    private function getAssociationByStorageName(string $name, FieldCollection $fields): ?ManyToOneAssociationField
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
}
