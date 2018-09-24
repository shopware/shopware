<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\JsonField;
use Shopware\Core\Framework\ORM\Field\ListField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\PriceField;
use Shopware\Core\Framework\ORM\Field\PriceRulesJsonField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Shopware\Core\Framework\ORM\Write\Flag\Inherited;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Uuid;
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

    public function hydrate(string $entity, string $definition, array $rows, string $root): array
    {
        /** @var EntityDefinition|string $definition */
        $collection = [];
        $this->objects = [];

        foreach ($rows as $row) {
            $collection[] = $this->hydrateEntity(new $entity(), $definition, $row, $root);
        }

        return $collection;
    }

    private function hydrateEntity(Entity $entity, string $definition, array $row, string $root): Entity
    {
        /** @var EntityDefinition $definition */
        $fields = $definition::getFields()->getElements();

        $idProperty = $root . '.id';

        $objectCacheKey = null;

        if (isset($row[$idProperty])) {
            $objectCacheKey = $definition::getEntityName() . '::' . bin2hex($row[$idProperty]);

            if (isset($this->objects[$objectCacheKey])) {
                return $this->objects[$objectCacheKey];
            }
        }

        $data = [];
        $associations = [];
        $inheritedFields = [];
        $inherited = new ArrayStruct();
        $translated = new ArrayStruct();

        /** @var Field $field */
        foreach ($fields as $field) {
            $propertyName = $field->getPropertyName();

            $originalKey = $root . '.' . $propertyName;

            $isInherited = $field->is(Inherited::class);

            if ($isInherited && $field instanceof AssociationInterface) {
                $inheritedFields[] = $field;
            }

            //collect to one associations, this will be hydrated after loop
            if ($field instanceof ManyToOneAssociationField) {
                $accessor = $root . '.' . $propertyName . '.id';

                if (isset($row[$accessor])) {
                    $associations[$propertyName] = $field;
                }

                continue;
            }

            if (!array_key_exists($originalKey, $row)) {
                continue;
            }

            $value = $row[$originalKey];
            //remove internal .inherited key which used to detect if a inherited field is selected by parent or child
            if ($isInherited) {
                $inheritedKey = '_' . $originalKey . '.inherited';

                if (isset($row[$inheritedKey])) {
                    $inherited->set($propertyName, (bool) $row[$inheritedKey]);
                }
            }

            //in case of a translated field, remove .translated element which is selected to detect if the value is translated in current language or contains the fallback
            if ($field instanceof TranslatedField) {
                $translationKey = '_' . $originalKey . '.translated';

                if (isset($row[$translationKey])) {
                    $translated->set($propertyName, (bool) $row[$translationKey]);
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
                /** @var ArrayStruct $extension */
                $extension = $extension;
                $extension->set($propertyName, $ids);
            }
        }

        /** @var AssociationInterface[] $associations */
        foreach ($associations as $property => $field) {
            $reference = $field->getReferenceClass();

            /** @var EntityDefinition $reference */
            $structClass = $reference::getStructClass();

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

        if ($definition::isInheritanceAware()) {
            /** @var Field $association */
            foreach ($inheritedFields as $association) {
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

                return new PriceStruct($value['net'], $value['gross'], (bool) $value['linked']);

            case $field instanceof PriceRulesJsonField:
                $value = json_decode((string) $value, true);

                $structs = [];
                if (isset($value['raw'])) {
                    foreach ($value['raw'] as $record) {
                        $structs[] = $this->serializer->deserialize(json_encode($record), '', 'json');
                    }
                }

                return new PriceRuleCollection($structs);

            case $field instanceof ObjectField:
                if ($value === null) {
                    return null;
                }

                return $this->serializer->deserialize($value, '', 'json');

            case $field instanceof ListField:
                if ($value === null) {
                    return [];
                }

                return json_decode($value, true);

            case $field instanceof JsonField:
                if ($value === null) {
                    return null;
                }

                return json_decode($value, true);

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

        if (!isset($row[$joinField])) {
            return false;
        }

        $idValue = $row[$idField];
        $joinValue = $row[$joinField];

        return $idValue !== $joinValue;
    }
}
