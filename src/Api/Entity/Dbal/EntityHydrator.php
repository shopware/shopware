<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\ArrayField;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\LongTextWithHtmlField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\Extension;

class EntityHydrator
{
    public static function hydrate(Entity $entity, string $definition, array $row, string $root): Entity
    {
        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        $data = [];
        $toOneAssociations = [];
        foreach ($row as $originalKey => $value) {
            $field = self::getField($fields, $originalKey, $root);

            if (!$field) {
                continue;
            }

            if (!$field instanceof AssociationInterface) {
                //reduce data set for nested calls
                $data[$field->getPropertyName()] = self::castValue($field, $value);
                unset($row[$originalKey]);
                continue;
            }

            if ($field instanceof ManyToOneAssociationField) {
                if (self::isManyToOneLoaded($field, $row, $root)) {
                    $toOneAssociations[$field->getPropertyName()] = $field;
                }
                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $property = implode('.', [$root, $field->getPropertyName()]);

                $ids = array_filter(explode('|', (string) $row[$property]));
                $ids = array_map(function (string $bytes) {
                    return Uuid::fromBytes($bytes)->toString();
                }, $ids);

                $data[$field->getStructIdMappingProperty()] = $ids;
                continue;
            }
        }

        /** @var AssociationInterface[] $toOneAssociations */
        foreach ($toOneAssociations as $property => $field) {
            $reference = $field->getReferenceClass();

            /** @var EntityDefinition $reference */
            $structClass = $reference::getBasicStructClass();

            $hydrated = self::hydrate(
                new $structClass(),
                $field->getReferenceClass(),
                $row,
                implode('.', [$root, $property])
            );

            /** @var Field $field */
            if ($field->is(Extension::class)) {
                $entity->addExtension($property, $hydrated);
            }
            $data[$property] = $hydrated;
        }

        return $entity->assign($data);
    }

    private static function getField(FieldCollection $fields, string $fieldName, string $root): ?Field
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

        $key = self::stripAssociationKey($key);
        if (!$key) {
            return null;
        }

        return $fields->get($key);
    }

    private static function stripAssociationKey(string $key): ?string
    {
        if (strpos($key, '.') === false) {
            return null;
        }
        $parts = explode('.', $key);

        return array_shift($parts);
    }

    private static function castValue(Field $field, $value)
    {
        switch (true) {
            case $field instanceof FkField:
            case $field instanceof IdField:
                if ($value === null) {
                    return null;
                }

                return Uuid::fromBytes($value)->toString();
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
            case $field instanceof ArrayField:
                return json_decode((string) $value, true);
            case $field instanceof LongTextField:
            case $field instanceof LongTextWithHtmlField:
            case $field instanceof StringField:
            default:
                return $value === null ? null : (string) $value;
        }
    }

    private static function isManyToOneLoaded(ManyToOneAssociationField $field, array $row, string $root): bool
    {
        $name = implode('.', [$root, $field->getPropertyName(), $field->getReferenceField()]);

        return isset($row[$name]);
    }
}
