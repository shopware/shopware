<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Context\Struct\TranslationContext;

class EntityDefinitionResolver
{
    public const REQUIRES_GROUP_BY = 'has_to_many_join';

    public static function escape(string $string): string
    {
        return '`' . $string . '`';
    }

    public static function getField(string $fieldName, string $definition, string $root): ?Field
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        $isAssociation = strpos($fieldName, '.') !== false;

        if (!$isAssociation && $fields->has($fieldName)) {
            return $fields->get($fieldName);
        }
        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        return self::getField(
            $original,
            $referenceClass,
            implode('.', [$root, $field->getPropertyName()])
        );
    }

    public static function resolveField(string $fieldName, string $definition, string $root): string
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        if ($fields->has($fieldName)) {
            $field = $fields->get($fieldName);

            if ($field instanceof TranslatedField) {
                return implode('.', [
                    self::escape($root . '.' . 'translation'),
                    self::escape($field->getStorageName()),
                ]);
            }

            if ($field instanceof StorageAware) {
                return implode('.', [
                    self::escape($root),
                    self::escape($field->getStorageName()),
                ]);
            }
        }

        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        if (!$fields->has($associationKey)) {
            throw new \RuntimeException(sprintf('Unmapped field %s for definition class', $original));
        }

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        return self::resolveField(
            $original,
            $referenceClass,
            implode('.', [$root, $field->getPropertyName()])
        );
    }

    public static function joinField(string $fieldName, string $definition, string $root, QueryBuilder $query, TranslationContext $context): void
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        if ($fields->has($fieldName)) {
            $field = $fields->get($fieldName);

            if ($field instanceof TranslatedField) {
                self::joinTranslation($root, $definition, $query, $context);
            }

            return;
        }

        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        if (!$fields->has($associationKey)) {
            return;
        }

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        if (!$field) {
            return;
        }

        $referenceClass = null;

        if ($field instanceof ManyToOneAssociationField) {
            self::joinManyToOne($root, $field, $query);
            $referenceClass = $field->getReferenceClass();
        }

        if ($field instanceof OneToManyAssociationField) {
            self::joinOneToMany($root, $field, $query);
            $query->addState(self::REQUIRES_GROUP_BY);
            $referenceClass = $field->getReferenceClass();
        }

        if ($field instanceof ManyToManyAssociationField) {
            self::joinManyToMany($root, $field, $query);
            $query->addState(self::REQUIRES_GROUP_BY);
            $referenceClass = $field->getReferenceDefinition();
        }

        if ($referenceClass === null) {
            throw new \RuntimeException(
                sprintf('Reference class can not be detected for association %s', get_class($field))
            );
        }

        self::joinField(
            $original,
            $referenceClass,
            implode('.', [$root, $field->getPropertyName()]),
            $query,
            $context
        );
    }

    public static function joinManyToOne(string $root, ManyToOneAssociationField $field, QueryBuilder $query): void
    {
        /** @var EntityDefinition $class */
        $class = $field->getReferenceClass();

        $table = $class::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            sprintf(
                '%s.%s = %s.%s',
                self::escape($root),
                self::escape($field->getStorageName()),
                self::escape($alias),
                self::escape($field->getReferenceField())
            )
        );
    }

    public static function joinOneToMany(string $root, OneToManyAssociationField $field, QueryBuilder $query): void
    {
        /** @var EntityDefinition $class */
        $class = $field->getReferenceClass();

        $table = $class::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            sprintf(
                '%s.%s = %s.%s',
                self::escape($root),
                self::escape($field->getLocalField()),
                self::escape($alias),
                self::escape($field->getReferenceField())
            )
        );
    }

    public static function joinManyToMany(string $root, ManyToManyAssociationField $field, QueryBuilder $query): void
    {
        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();
        $table = $mapping::getEntityName();

        $mappingAlias = $root . '.' . $field->getPropertyName() . '.mapping';
        if ($query->hasState($mappingAlias)) {
            return;
        }
        $query->addState($mappingAlias);

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($mappingAlias),
            sprintf(
                '%s.%s = %s.%s',
                self::escape($root),
                self::escape('id'),
                self::escape($mappingAlias),
                self::escape($field->getMappingLocalColumn())
            )
        );

        /** @var EntityDefinition $reference */
        $reference = $field->getReferenceDefinition();
        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        $query->leftJoin(
            self::escape($mappingAlias),
            self::escape($table),
            self::escape($alias),
            sprintf(
                '%s.%s = %s.%s',
                self::escape($mappingAlias),
                self::escape($field->getMappingReferenceColumn()),
                self::escape($alias),
                self::escape('id')
            )
        );
    }

    public static function joinTranslation(string $root, string $definition, QueryBuilder $query, TranslationContext $context): void
    {
        $alias = $root . '.translation';
        if ($query->hasState($alias)) {
            return;
        }

        $query->addState($alias);

        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName() . '_translation';

        $languageId = Uuid::fromString($context->getShopId())->getBytes();
        $query->setParameter('languageId', $languageId);

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            sprintf(
                '%s.%s_id = %s.id AND %s.language_id = :languageId',
                self::escape($alias),
                $definition::getEntityName(),
                self::escape($root),
                self::escape($alias)
            )
        );

        if (!$context->hasFallback()) {
            return;
        }

        $alias = $root . '.translation.fallback';

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            sprintf(
                '%s.%s_id = %s.id AND %s.language_id = :fallbackLanguageId',
                self::escape($alias),
                $definition::getEntityName(),
                self::escape($root),
                self::escape($alias)
            )
        );
        $languageId = Uuid::fromString($context->getFallbackId())->getBytes();
        $query->setParameter('fallbackLanguageId', $languageId);
    }

    public static function addTranslationSelect(string $root, string $definition, QueryBuilder $query, TranslationContext $context, FieldCollection $fields): void
    {
        self::joinTranslation($root, $definition, $query, $context);

        $alias = $root . '.translation';

        if (!$context->hasFallback()) {
            /** @var TranslatedField $field */
            foreach ($fields->getElements() as $property => $field) {
                $query->addSelect(
                    self::escape($alias) . '.' . self::escape($field->getStorageName())
                    . ' as ' .
                    self::escape($alias . '.' . $property)
                );
            }

            return;
        }

        $fallback = $root . '.translation.fallback';
        /** @var TranslatedField $field */
        foreach ($fields->getElements() as $property => $field) {
            $select = sprintf(
                'COALESCE(%s.%s, %s.%s) as %s',
                self::escape($alias),
                self::escape($field->getStorageName()),
                self::escape($fallback),
                self::escape($field->getStorageName()),
                self::escape($alias . '.' . $field->getPropertyName())
            );

            $query->addSelect($select);
        }
    }

    public static function uuidStringsToBytes(array $ids)
    {
        return array_map(function (string $id) {
            return Uuid::fromString($id)->getBytes();
        }, $ids);
    }
}
