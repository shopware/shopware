<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * Allows to hydrate database values into struct objects.
 */
class EntityHydrator
{
    /**
     * @var Entity[] internal object cache to prevent duplicate hydration for exact same objects
     */
    private $objects = [];

    public function hydrate(EntityCollection $collection, string $entityClass, EntityDefinition $definition, array $rows, string $root, Context $context): EntityCollection
    {
        $this->objects = [];

        foreach ($rows as $row) {
            $collection->add($this->hydrateEntity(new $entityClass(), $definition, $row, $root, $context));
        }

        return $collection;
    }

    public static function buildUniqueIdentifier(EntityDefinition $definition, array $row, string $root): array
    {
        $primaryKeyFields = $definition->getPrimaryKeys();
        $primaryKey = [];

        /** @var Field $field */
        foreach ($primaryKeyFields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }
            $accessor = $root . '.' . $field->getPropertyName();

            $primaryKey[$field->getPropertyName()] = $field->getSerializer()->decode($field, $row[$accessor]);
        }

        return $primaryKey;
    }

    public static function encodePrimaryKey(EntityDefinition $definition, array $primaryKey, Context $context): array
    {
        $fields = $definition->getPrimaryKeys();

        $mapped = [];

        $existence = new EntityExistence($definition->getEntityName(), [], true, false, false, []);

        $params = new WriteParameterBag($definition, WriteContext::createFromContext($context), '', new WriteCommandQueue());

        /** @var Field $field */
        foreach ($fields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                $value = $context->getVersionId();
            } else {
                $value = $primaryKey[$field->getPropertyName()];
            }

            $kvPair = new KeyValuePair($field->getPropertyName(), $value, true);

            $encoded = $field->getSerializer()->encode($field, $existence, $kvPair, $params);

            foreach ($encoded as $key => $value) {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    private function hydrateEntity(Entity $entity, EntityDefinition $definition, array $row, string $root, Context $context): Entity
    {
        $fields = $definition->getFields();

        $identifier = self::buildUniqueIdentifier($definition, $row, $root);
        $identifier = implode('-', $identifier);

        $entity->setUniqueIdentifier($identifier);

        $cacheKey = $definition->getEntityName() . '::' . $identifier;
        if (isset($this->objects[$cacheKey])) {
            return $this->objects[$cacheKey];
        }

        $mappingStorage = new ArrayEntity([]);
        $entity->addExtension(EntityReader::INTERNAL_MAPPING_STORAGE, $mappingStorage);

        $foreignKeys = new ArrayEntity([]);
        $entity->addExtension(EntityReader::FOREIGN_KEYS, $foreignKeys);

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
            if ($field instanceof AssociationField) {
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

            if ($typedField instanceof CustomFields) {
                $this->hydrateCustomFields($root, $field, $typedField, $entity, $row, $context);

                continue;
            }

            if ($field instanceof TranslatedField) {
                // contains the resolved translation chain value
                $decoded = $typedField->getSerializer()->decode($typedField, $value);
                $entity->addTranslated($propertyName, $decoded);

                // assign translated value of the first language
                $key = $root . '.translation.' . $propertyName;
                $decoded = $typedField->getSerializer()->decode($typedField, $row[$key]);
                $entity->assign([$propertyName => $decoded]);

                continue;
            }

            $decoded = $field->getSerializer()->decode($field, $value);

            if ($field->is(Extension::class)) {
                $foreignKeys->set($propertyName, $decoded);
            } else {
                $entity->assign([$propertyName => $decoded]);
            }
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
    private function mergeJson(array $jsonStrings): string
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

    private function hydrateManyToOne(array $row, string $root, Context $context, AssociationField $field): ?Entity
    {
        if (!$field instanceof OneToOneAssociationField && !$field instanceof ManyToOneAssociationField) {
            return null;
        }

        $reference = $field->getReferenceDefinition();

        $pkField = $reference->getFields()->getByStorageName(
            $field->getReferenceField()
        );

        $key = $root . '.' . $field->getPropertyName() . '.' . $pkField->getPropertyName();

        //check if ManyToOne is loaded (`product.manufacturer.id`). Otherwise the association is set to null and continue
        if (!isset($row[$key])) {
            return null;
        }

        $structClass = $reference->getEntityClass();

        return $this->hydrateEntity(
            new $structClass(),
            $reference,
            $row,
            $root . '.' . $field->getPropertyName(),
            $context
        );
    }

    private function hydrateCustomFields(string $root, Field $field, CustomFields $customField, Entity $entity, array $row, Context $context): void
    {
        $inherited = $field->is(Inherited::class) && $context->considerInheritance();

        $propertyName = $field->getPropertyName();

        $key = $root . '.' . $propertyName;

        $value = $row[$key];

        if ($field instanceof TranslatedField) {
            $key = $root . '.translation.' . $propertyName;
            $decoded = $customField->getSerializer()->decode($customField, $row[$key]);
            $entity->assign([$propertyName => $decoded]);

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
            $entity->addTranslated($propertyName, $customField->getSerializer()->decode($customField, $merged));

            return;
        }

        // field is not inherited or request should work with raw data? decode child attributes and return
        if (!$inherited) {
            $value = $customField->getSerializer()->decode($customField, $value);
            $entity->assign([$propertyName => $value]);

            return;
        }

        $parentKey = $root . '.' . $propertyName . '.inherited';

        // parent has no attributes? decode only child attributes and return
        if (!isset($row[$parentKey])) {
            $value = $customField->getSerializer()->decode($customField, $value);

            $entity->assign([$propertyName => $value]);

            return;
        }

        // merge child attributes with parent attributes and assign
        $mergedJson = $this->mergeJson([$row[$parentKey], $value]);

        $merged = $customField->getSerializer()->decode($customField, $mergedJson);

        $entity->assign([$propertyName => $merged]);
    }
}
