<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Shopware\Api\Context\Collection\ContextPriceCollection;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\ContextPricesJsonField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\JsonArrayField;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\LongTextWithHtmlField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\PriceField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Api\Entity\Write\Flag\Serialized;
use Shopware\Api\Product\Struct\PriceStruct;
use Shopware\Framework\Struct\ArrayStruct;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Allows to hydrate database values into struct objects.
 */
class EntityHydrator
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    private $fieldCache = [];

    private $objects = [];

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function hydrate(Entity $entity, string $definition, array $rows, string $root): array
    {
        /** @var EntityDefinition|string $definition */
        $collection = [];
        $this->objects = [];

        foreach ($rows as $row) {
            $collection[] = $this->hydrateEntity(clone $entity, $definition, $row, $root);
        }

        return $collection;
    }

    private function hydrateEntity(Entity $entity, string $definition, array $row, string $root): Entity
    {
        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        $idProperty = $root . '.id';

        $objectCacheKey = null;

        if (array_key_exists($idProperty, $row)) {
            $objectCacheKey = $definition::getEntityName() . '::' . bin2hex($row[$idProperty]);
            if (array_key_exists($objectCacheKey, $this->objects)) {
                return $this->objects[$objectCacheKey];
            }
        }

        $data = [];
        $toOneAssociations = [];
        $inherited = new ArrayStruct();
        $translated = new ArrayStruct();

        foreach ($row as $originalKey => $value) {
            $field = $this->getField($fields, $originalKey, $root);

            if (!$field) {
                continue;
            }

            if ($field->is(Inherited::class)) {
                $inheritedKey = '_' . $originalKey . '.inherited';

                if (array_key_exists($inheritedKey, $row)) {
                    $inherited->set($field->getPropertyName(), (bool) $row[$inheritedKey]);
                    unset($row[$inheritedKey]);
                }
            }
            if ($field instanceof TranslatedField) {
                $translationKey = '_' . $originalKey . '.translated';

                if (array_key_exists($translationKey, $row)) {
                    $translated->set($field->getPropertyName(), (bool) $row[$translationKey]);
                    unset($row[$translationKey]);
                }
            }

            if (!$field instanceof AssociationInterface) {
                //reduce data set for nested calls
                $data[$field->getPropertyName()] = $this->castValue($field, $value);
                continue;
            }

            if ($field instanceof ManyToOneAssociationField) {
                if ($value !== null) {
                    $toOneAssociations[$field->getPropertyName()] = $field;
                }
                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $property = $root . '.' . $field->getPropertyName();

                $ids = explode('||', (string) $row[$property]);
                $ids = array_filter($ids);
                $ids = array_map('strtolower', $ids);

                $extension = $entity->getExtension(EntityReader::MANY_TO_MANY_EXTENSION_STORAGE);
                if (!$extension) {
                    $extension = new ArrayStruct([]);
                    $entity->addExtension(EntityReader::MANY_TO_MANY_EXTENSION_STORAGE, $extension);
                }

                $extension->set($field->getPropertyName(), $ids);
                continue;
            }
        }

        /** @var AssociationInterface[] $toOneAssociations */
        foreach ($toOneAssociations as $property => $field) {
            $reference = $field->getReferenceClass();

            /** @var EntityDefinition $reference */
            $structClass = $reference::getBasicStructClass();

            $hydrated = $this->hydrateEntity(
                new $structClass(),
                $field->getReferenceClass(),
                $row,
                $root . '.' . $property
            );

            /** @var Field $field */
            if ($field->is(Extension::class)) {
                $entity->addExtension($property, $hydrated);
            } else {
                $data[$property] = $hydrated;
            }
        }

        $entity->assign($data);

        if ($objectCacheKey) {
            $this->objects[$objectCacheKey] = $entity;
        }

        if ($definition::getParentPropertyName()) {
            $associations = $definition::getFields()
                ->filterByFlag(Inherited::class)
                ->filter(
                    function (Field $field) {
                        return $field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField;
                    }
                );

            foreach ($associations as $association) {
                $inherited->set($association->getPropertyName(), $this->isInherited($definition, $row, $association));
            }

            $entity->addExtension('inherited', $inherited);
        }

        if ($definition::getTranslationDefinitionClass()) {
            $entity->addExtension('translated', $translated);
        }

        return $entity;
    }

    private function findField(FieldCollection $fields, string $fieldName, string $root): ?Field
    {
        if (strpos($fieldName, $root . '.') !== 0) {
            return null;
        }

        $key = str_replace($root . '.', '', $fieldName);

        //is translation field? remove prefix
        if (strpos($key, 'translation.') === 0) {
            $key = str_replace('translation.', '', $key);
        }

        $field = $fields->get($key);
        if ($field) {
            return $field;
        }

        $key = $this->stripAssociationKey($key);
        if (!$key) {
            return null;
        }

        return $fields->get($key);
    }

    private function getField(FieldCollection $fields, string $fieldName, string $root): ?Field
    {
        $key = $root . '-' . $fieldName;
        if (array_key_exists($key, $this->fieldCache)) {
            return $this->fieldCache[$key];
        }

        $field = $this->findField($fields, $fieldName, $root);

        $this->fieldCache[$key] = $field;

        return $field;
    }

    private function stripAssociationKey(string $key): ?string
    {
        if (strpos($key, '.') === false) {
            return null;
        }
        $parts = explode('.', $key);

        return array_shift($parts);
    }

    private function castValue(Field $field, $value)
    {
        switch (true) {
            case $field instanceof VersionField:
            case $field instanceof ReferenceVersionField:
                //version fields are not stored in the struct data
                return null;

            case $field instanceof FkField:
            case $field instanceof IdField:
                if ($value === null) {
                    return null;
                }

                return Uuid::fromBytesToHex($value);
            case $field instanceof FloatField:
                return $value === null ? null : (float) $value;
            case $field instanceof IntField:
                return $value === null ? null : (int) $value;
            case $field instanceof BoolField:
                return (bool) $value;
            case $field instanceof TranslatedField:
                return $value === null ? null : (string) $value;
            case $field instanceof DateField:
                return $value === null ? null : new \DateTime($value);

            case $field instanceof PriceField:
                if ($value === null) {
                    return null;
                }
                $value = json_decode((string) $value, true);

                return new PriceStruct($value['net'], $value['gross']);

            case $field instanceof ContextPricesJsonField:
                $value = json_decode((string) $value, true);

                $structs = [];
                if (isset($value['raw'])) {
                    foreach ($value['raw'] as $record) {
                        $structs[] = $this->serializer->deserialize(json_encode($record), '', 'json');
                    }
                }

                return new ContextPriceCollection($structs);

            case $field instanceof JsonArrayField:
                if ($value === null) {
                    return null;
                }

                if (!$field->is(Serialized::class)) {
                    return json_decode($value, true);
                }

                $structs = [];
                $array = json_decode($value, true);
                if (!is_array($array)) {
                    return null;
                }
                foreach ($array as $item) {
                    $structs[] = $this->serializer->deserialize(json_encode($item), '', 'json');
                }

                return $structs;

            case $field instanceof JsonObjectField:
                if ($field->is(Serialized::class)) {
                    return $this->serializer->deserialize($value, '', 'json');
                }

                return json_decode((string) $value, true);

            case $field instanceof LongTextField:
            case $field instanceof LongTextWithHtmlField:
            case $field instanceof StringField:
            default:
                return $value === null ? null : (string) $value;
        }
    }

    private function isInherited(string $definition, array $row, AssociationInterface $association): bool
    {
        /* @var string|EntityDefinition $definition */
        $idField = 'id';
        if ($association instanceof ManyToOneAssociationField) {
            $idField = $association->getStorageName();
        }

        $idField = $definition::getFields()->getByStorageName($idField);

        $joinField = '_' . $definition::getEntityName() . '.' . $association->getPropertyName() . '.inherited';
        $idField = $definition::getEntityName() . '.' . $idField->getPropertyName();

        if (!array_key_exists($joinField, $row)) {
            return false;
        }

        $idValue = $row[$idField];
        $joinValue = $row[$joinField];

        return $idValue !== $joinValue;
    }
}
