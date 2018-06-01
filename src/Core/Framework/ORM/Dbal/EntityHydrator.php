<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal;

use Shopware\Application\Context\Collection\ContextPriceCollection;
use Shopware\Content\Product\Struct\PriceStruct;
use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\AssociationInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\ContextPricesJsonField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\JsonArrayField;
use Shopware\Framework\ORM\Field\JsonObjectField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\LongTextWithHtmlField;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\PriceField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\Write\Flag\Extension;
use Shopware\Framework\ORM\Write\Flag\Inherited;
use Shopware\Framework\ORM\Write\Flag\Serialized;
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

    private function isManyToOneLoaded(ManyToOneAssociationField $field, string $root, array $row)
    {
        $keys = $field->getReferenceClass()::getPrimaryKeys();

        foreach ($keys as $key) {
            $nested = $root . '.' . $field->getPropertyName() . '.' . $key->getPropertyName();

            if (isset($row[$nested])) {
                return true;
            }
        }

        return false;
    }

    private function hydrateEntity(Entity $entity, string $definition, array $row, string $root): Entity
    {
        /** @var EntityDefinition $definition */
        $fields = $definition::getFields()->getElements();

        $idProperty = $root . '.id';

        $objectCacheKey = null;

        if (array_key_exists($idProperty, $row)) {
            $objectCacheKey = $definition::getEntityName() . '::' . bin2hex($row[$idProperty]);

            if (array_key_exists($objectCacheKey, $this->objects)) {
                return $this->objects[$objectCacheKey];
            }
        }

        $data = [];
        $associations = [];
        $inherited = new ArrayStruct();
        $translated = new ArrayStruct();

        foreach ($fields as $field) {
            $originalKey = $root . '.' . $field->getPropertyName();

            //collect to one associations, this will be hydrated after loop
            if ($field instanceof ManyToOneAssociationField) {
                if ($this->isManyToOneLoaded($field, $root, $row)) {
                    $associations[$field->getPropertyName()] = $field;
                }

                continue;
            }

            if (!array_key_exists($originalKey, $row)) {
                continue;
            }

            $value = $row[$originalKey];

            $propertyName = $field->getPropertyName();

            //remove internal .inherited key which used to detect if a inherited field is selected by parent or child
            if ($field->is(Inherited::class)) {
                $inheritedKey = '_' . $originalKey . '.inherited';

                if (array_key_exists($inheritedKey, $row)) {
                    $inherited->set($propertyName, (bool) $row[$inheritedKey]);
                    unset($row[$inheritedKey]);
                }
            }

            //in case of a translated field, remove .translated element which is selected to detect if the value is translated in current language or contains the fallback
            if ($field instanceof TranslatedField) {
                $translationKey = '_' . $originalKey . '.translated';

                if (array_key_exists($translationKey, $row)) {
                    $translated->set($propertyName, (bool) $row[$translationKey]);
                    unset($row[$translationKey]);
                }
            }

            //scalar data values can be casted directly
            if (!$field instanceof AssociationInterface) {
                //reduce data set for nested calls
                $data[$propertyName] = $this->castValue($field, $value);
                continue;
            }

            //many to many fields contains a group concat id value in the selection, this will be stored in an internal extension to collect them later
            if ($field instanceof ManyToManyAssociationField) {
                $property = $root . '.' . $propertyName;

                $ids = explode('||', (string) $row[$property]);
                $ids = array_filter($ids);
                $ids = array_map('strtolower', $ids);

                $extension = $entity->getExtension(EntityReader::MANY_TO_MANY_EXTENSION_STORAGE);
                if (!$extension) {
                    $extension = new ArrayStruct([]);
                    $entity->addExtension(EntityReader::MANY_TO_MANY_EXTENSION_STORAGE, $extension);
                }

                $extension->set($propertyName, $ids);
                continue;
            }
        }

        /** @var AssociationInterface[] $associations */
        foreach ($associations as $property => $field) {
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

        //write object cache key to prevent multiple hydration for the same entity
        if ($objectCacheKey) {
            $this->objects[$objectCacheKey] = $entity;
        }

        if ($definition::getParentPropertyName()) {
            $associations = $definition::getFields()->getElements();
            /** @var Field $association */
            foreach ($associations as $association) {
                if (!$association->is(Inherited::class)) {
                    continue;
                }

                if (!$association instanceof ManyToManyAssociationField && !$association instanceof OneToManyAssociationField) {
                    continue;
                }

                $inherited->set($association->getPropertyName(), $this->isInherited($definition, $row, $association));
            }

            $entity->addExtension('inherited', $inherited);
        }

        if ($definition::getTranslationDefinitionClass()) {
            $entity->addExtension('translated', $translated);
        }

        return $entity;
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
