<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerRegistry;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * Allows to hydrate database values into struct objects.
 */
class EntityHydrator
{
    /**
     * @var FieldSerializerRegistry
     */
    private $fieldHandler;

    /**
     * @var Entity[] internal object cache to prevent duplicate hydration for exact same objects
     */
    private $objects = [];

    public function __construct(FieldSerializerRegistry $fieldHandler)
    {
        $this->fieldHandler = $fieldHandler;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    public function hydrate(string $entity, string $definition, array $rows, string $root, Context $context): array
    {
        $collection = [];
        $this->objects = [];

        foreach ($rows as $row) {
            $collection[] = $this->hydrateEntity(new $entity(), $definition, $row, $root, $context);
        }

        return $collection;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function hydrateEntity(Entity $entity, string $definition, array $row, string $root, Context $context): Entity
    {
        $fields = $definition::getFields();

        $identifier = $this->buildPrimaryKey($definition, $row, $root);
        $identifier = implode('-', $identifier);

        $entity->setUniqueIdentifier($identifier);

        $cacheKey = $definition::getEntityName() . '::' . $identifier;
        if (isset($this->objects[$cacheKey])) {
            return $this->objects[$cacheKey];
        }

        $mappingStorage = new ArrayEntity([]);
        $entity->addExtension(EntityReader::INTERNAL_MAPPING_STORAGE, $mappingStorage);

        /** @var Field $field */
        foreach ($fields as $field) {
            $propertyName = $field->getPropertyName();

            $originalKey = $root . '.' . $propertyName;

            //skip parent association to prevent endless loop. Additionally the reader do now allow to access parent values
            if ($field instanceof ParentAssociationField) {
                continue;
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

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                //hydrated contains now the associated entity (eg. currently hydrating the product, hydrated contains now the manufacturer or tax or ...)
                $hydrated = $this->hydrateManyToOne($row, $root, $context, $field);

                if ($field->is(Extension::class)) {
                    $entity->addExtension($propertyName, $hydrated);
                } else {
                    $entity->assign([$propertyName => $hydrated]);
                }

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

            $typedField = $field;
            if ($field instanceof TranslatedField) {
                $typedField = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
            }

            if ($typedField instanceof AttributesField) {
                $this->hydrateAttributes($root, $field, $typedField, $entity, $row, $context);
                continue;
            }

            if ($field instanceof TranslatedField) {
                // contains the resolved translation chain value
                $decoded = $this->fieldHandler->decode($typedField, $value);
                $entity->addTranslated($propertyName, $decoded);

                // assign translated value of the first language
                $key = $root . '.translation.' . $propertyName;
                $decoded = $this->fieldHandler->decode($typedField, $row[$key]);
                $entity->assign([$propertyName => $decoded]);

                continue;
            }

            $decoded = $this->fieldHandler->decode($field, $value);
            $entity->assign([$propertyName => $decoded]);
        }

        //write object cache key to prevent multiple hydration for the same entity
        if ($cacheKey) {
            $this->objects[$cacheKey] = $entity;
        }

        return $entity;
    }

    /**
     * @param string[] $jsonStrings
     */
    private function mergeJson(array $jsonStrings): ?string
    {
        $merged = [];
        foreach ($jsonStrings as $string) {
            $decoded = json_decode((string) $string, true);
            if (!$decoded) {
                continue;
            }
            foreach ($decoded as $key => $value) {
                $merged[$key] = $value;
            }
        }

        return json_encode($merged, JSON_PRESERVE_ZERO_FRACTION);
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

    /**
     * @param string|EntityDefinition $definition
     */
    private function buildPrimaryKey($definition, array $row, string $root): array
    {
        $primaryKeyFields = $definition::getPrimaryKeys();
        $primaryKey = [];

        /** @var Field $field */
        foreach ($primaryKeyFields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }
            $accessor = $root . '.' . $field->getPropertyName();

            $primaryKey[$field->getPropertyName()] = $this->fieldHandler->decode($field, $row[$accessor]);
        }

        return $primaryKey;
    }

    private function hydrateManyToOne(array $row, string $root, Context $context, AssociationInterface $field): ?Entity
    {
        /** @var OneToOneAssociationField $field */
        if (!$field instanceof OneToOneAssociationField && !$field instanceof ManyToOneAssociationField) {
            return null;
        }

        $pkField = $field->getReferenceClass()::getFields()->getByStorageName(
            $field->getReferenceField()
        );

        $key = $root . '.' . $field->getPropertyName() . '.' . $pkField->getPropertyName();

        //check if ManyToOne is loaded (`product.manufacturer.id`). Otherwise the association is set to null and continue
        if (!isset($row[$key])) {
            return null;
        }

        $structClass = $field->getReferenceClass()::getEntityClass();

        return $this->hydrateEntity(
            new $structClass(),
            $field->getReferenceClass(),
            $row,
            $root . '.' . $field->getPropertyName(),
            $context
        );
    }

    private function hydrateAttributes(string $root, Field $field, AttributesField $attributesField, Entity $entity, array $row, Context $context): void
    {
        $inherited = $field->is(Inherited::class) && $context->considerInheritance();

        $propertyName = $field->getPropertyName();

        $key = $root . '.' . $propertyName;

        $value = $row[$key];

        if ($field instanceof TranslatedField) {
            $entity->assign([
                $propertyName => $this->fieldHandler->decode($attributesField, $value),
            ]);

            $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $inherited);

            $values = [];
            foreach ($chain as $part) {
                $key = $part['alias'] . '.' . $propertyName;
                $values[] = $row[$key] ?? null;
            }

            if (empty($values)) {
                return;
            }

            /**
             * `array_merge`s ordering is reversed compared to the translations array.
             * In other terms: The first argument has the lowest 'priority', so we need to reverse the array
             */
            $merged = $this->mergeJson(\array_reverse($values, false));
            $entity->addTranslated($propertyName, $this->fieldHandler->decode($attributesField, $merged));

            return;
        }

        // field is not inherited or request should work with raw data? decode child attributes and return
        if (!$inherited) {
            $value = $this->fieldHandler->decode($attributesField, $value);
            $entity->assign([$propertyName => $value]);

            return;
        }

        $parentKey = $root . '.' . $propertyName . '.inherited';

        // parent has no attributes? decode only child attributes and return
        if (!isset($row[$parentKey])) {
            $value = $this->fieldHandler->decode($attributesField, $value);

            $entity->assign([$propertyName => $value]);

            return;
        }

        // merge child attributes with parent attributes and assign
        $mergedJson = $this->mergeJson([$row[$parentKey], $value]);

        $merged = $this->fieldHandler->decode($attributesField, $mergedJson);

        $entity->assign([$propertyName => $merged]);
    }
}
