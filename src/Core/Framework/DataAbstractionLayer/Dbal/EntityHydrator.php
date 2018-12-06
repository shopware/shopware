<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * Allows to hydrate database values into struct objects.
 */
class EntityHydrator
{
    /**
     * @var FieldSerializerRegistry
     */
    private $fieldHandler;

    private $objects = [];

    public function __construct(FieldSerializerRegistry $fieldHandler)
    {
        $this->fieldHandler = $fieldHandler;
    }

    public function hydrate(string $entity, string $definition, array $rows, string $root, Context $context): array
    {
        /** @var EntityDefinition|string $definition */
        $collection = [];
        $this->objects = [];

        foreach ($rows as $row) {
            $collection[] = $this->hydrateEntity(new $entity(), $definition, $row, $root, $context);
        }

        return $collection;
    }

    private function hydrateEntity(Entity $entity, string $definition, array $row, string $root, Context $context): Entity
    {
        /** @var EntityDefinition $definition */
        $fields = $definition::getFields()->getElements();

        $identifier = $this->buildPrimaryKey($definition, $row, $root);
        $identifier = implode('-', $identifier);

        $entity->setUniqueIdentifier($identifier);

        $cacheKey = null;
        if (isset($row[$identifier])) {
            $cacheKey = $definition::getEntityName() . '::' . $identifier;

            if (isset($this->objects[$cacheKey])) {
                return $this->objects[$cacheKey];
            }
        }

        $entity->setViewData(clone $entity);

        $mappingStorage = new ArrayStruct([]);
        $entity->addExtension(EntityReader::MANY_TO_MANY_EXTENSION_STORAGE, $mappingStorage);

        /** @var Field $field */
        foreach ($fields as $field) {
            $propertyName = $field->getPropertyName();

            $originalKey = $root . '.' . $propertyName;

            //skip parent association to prevent endless loop. Additionally the reader do now allow to access parent values
            if ($field instanceof ParentAssociationField) {
                continue;
            }

            if ($field instanceof AssociationInterface && $field->is(Inherited::class)) {
                $key = $originalKey . '.owner';

                if (array_key_exists($key, $row)) {
                    $mappingStorage->set($key, $row[$key]);
                }
            }

            //many to many fields contains a group concat id value in the selection, this will be stored in an internal extension to collect them later
            if ($field instanceof ManyToManyAssociationField) {
                $ids = $this->extractManyToManyIds($root, $field, $row);

                if ($ids === null) {
                    continue;
                }

                //add many to many mapping to internal storage for further usages in entity reader (see entity reader \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::loadManyToManyOverExtension)
                $mappingStorage->set($propertyName, $ids);

                continue;
            }

            if ($field instanceof ManyToOneAssociationField) {
                //hydrated contains now the associated entity (eg. currently hydrating the product, hydrated contains now the manufacturer or tax or ...)
                $hydrated = $this->hydrateManyToOne($row, $root, $context, $field);

                //.owner field contains the value of the foreign key of the child row.
                $key = $originalKey . '.owner';
                if (isset($row[$key])) {
                    $entity->assign([$propertyName => $hydrated]);
                }

                $entity->getViewData()->assign([$propertyName => $hydrated]);

                continue;
            }

            //other association fields are not handled in entity reader query
            if ($field instanceof AssociationInterface) {
                continue;
            }

            /* @var StorageAware $field */
            if (!array_key_exists($originalKey, $row)) {
                continue;
            }

            $value = $row[$originalKey];

            //handle resolved language inheritance
            if ($field instanceof TranslatedField) {
                //decode resolved language inheritance (only assigned to `viewData`)
                $translatedField = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
                $decoded = $this->fieldHandler->decode($translatedField, $value);
                $entity->getViewData()->assign([$propertyName => $decoded]);

                //decode raw language data (only assigned to `child` entity)
                $key = $root . '.translation.' . $propertyName;
                $decoded = $this->fieldHandler->decode($translatedField, $row[$key]);
                $entity->assign([$propertyName => $decoded]);

                continue;
            }

            if ($field->is(Inherited::class)) {
                //decode resolved inheritance value (only assigned to `viewData`)
                $decoded = $this->fieldHandler->decode($field, $value);
                $entity->getViewData()->assign([$propertyName => $decoded]);

                //decode raw child value (only assigned to `child` entity)
                $key = $root . '.' . $propertyName . '.raw';
                $decoded = $this->fieldHandler->decode($field, $row[$key]);
                $entity->assign([$propertyName => $decoded]);

                continue;
            }

            $decoded = $this->fieldHandler->decode($field, $value);
            $entity->assign([$propertyName => $decoded]);
            $entity->getViewData()->assign([$propertyName => $decoded]);
        }

        $translations = $this->hydrateTranslations($definition, $root, $row, $context);

        if ($translations !== null) {
            $entity->assign(['translations' => $translations]);
        }

        //write object cache key to prevent multiple hydration for the same entity
        if ($cacheKey) {
            $this->objects[$cacheKey] = $entity;
        }

        return $entity;
    }

    private function extractManyToManyIds(string $root, ManyToManyAssociationField $field, array $row): ?array
    {
        $accessor = $root . '.' . $field->getPropertyName() . '.id_mapping';

        //many to many isn't loaded in case of limited association criterias
        if (!array_key_exists($accessor, $row)) {
            return null;
        }

        //explode hexed ids
        $ids = explode('||', (string) $row[$accessor]);

        //sql do not cast to lower
        return array_map('strtolower', array_filter($ids));
    }

    private function hydrateTranslations(string $definition, string $root, array $row, Context $context): ?EntityCollection
    {
        /** @var string|EntityDefinition $definition */
        $translationDefinition = $definition::getTranslationDefinitionClass();

        if ($translationDefinition === null) {
            return null;
        }

        /** @var string|EntityDefinition $translationDefinition */
        $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $definition, $context, true);

        $structClass = $translationDefinition::getStructClass();

        $collection = $translationDefinition::getCollectionClass();

        /** @var EntityCollection $collection */
        $collection = new $collection();

        //builds a complete collection with translation structs of the current entity
        foreach ($chain as $accessor) {
            $entity = $this->hydrateEntity(new $structClass(), $translationDefinition, $row, $accessor, $context);
            $collection->add($entity);
        }

        return $collection;
    }

    private function buildPrimaryKey($definition, array $row, string $root): array
    {
        /** @var string|EntityDefinition $definition */
        $primaryKeyFields = $definition::getFields()->filterByFlag(PrimaryKey::class);
        $primaryKey = [];

        foreach ($primaryKeyFields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }
            $accessor = $root . '.' . $field->getPropertyName();

            $primaryKey[$field->getPropertyName()] = $this->fieldHandler->decode($field, $row[$accessor]);
        }

        return $primaryKey;
    }

    private function hydrateManyToOne(array $row, string $root, Context $context, ManyToOneAssociationField $field): ?Entity
    {
        $key = $root . '.' . $field->getPropertyName() . '.id';

        //check if ManyToOne is loaded (`product.manufacturer.id`). Otherwise the association is set to null and continue
        if (!isset($row[$key])) {
            return null;
        }

        /** @var EntityDefinition $reference */
        $structClass = $field->getReferenceClass()::getStructClass();

        return $this->hydrateEntity(new $structClass(), $field->getReferenceClass(), $row, $root . '.' . $field->getPropertyName(), $context);
    }
}
