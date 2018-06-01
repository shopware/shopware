<?php declare(strict_types=1);

namespace Shopware\Framework\ORM;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Field\AssociationInterface;
use Shopware\Framework\ORM\Field\ChildCountField;
use Shopware\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Write\EntityExistence;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Framework\ORM\Write\WrittenEvent;

abstract class EntityDefinition
{
    public const HIGH_SEARCH_RANKING = 500;
    public const MIDDLE_SEARCH_RANKING = 250;
    public const LOW_SEARCH_RAKING = 80;
    public const ASSOCIATION_SEARCH_RANKING = 0.25;

    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function addExtension(EntityExtensionInterface $extension): void
    {
        static::$extensions[get_class($extension)] = $extension;
        static::$fields = null;
    }

    abstract public static function getEntityName(): string;

    abstract public static function getFields(): FieldCollection;

    abstract public static function getRepositoryClass(): string;

    abstract public static function getBasicCollectionClass(): string;

    abstract public static function getBasicStructClass(): string;

    abstract public static function getWrittenEventClass(): string;

    abstract public static function getDeletedEventClass(): string;

    abstract public static function getTranslationDefinitionClass(): ?string;

    public static function getWriteOrder(): array
    {
        $associations = static::getFields()->filter(function (Field $field) {
            return $field instanceof AssociationInterface && !$field->is(ReadOnly::class);
        });

        $manyToOne = static::filterAssociationReferences(ManyToOneAssociationField::class, $associations);

        $oneToMany = static::filterAssociationReferences(OneToManyAssociationField::class, $associations);

        $manyToMany = static::filterAssociationReferences(ManyToManyAssociationField::class, $associations);

        $self = array_filter([static::class, static::getTranslationDefinitionClass()]);

        /*
         * If a linked entity exists once as OneToMany but also as ManyToOne (bi-directional foreign keys),
         * it must be treated as OneToMany. In the MySQL database,
         * no foreign key may be created for the ManyToOne relation.
         *
         * Examples:
         *      a customer has 1:N addresses
         *      a customer has 1:1 default_shipping_address
         *      a customer has 1:1 default_billing_address
         */
        $c = array_intersect($manyToOne, $oneToMany);
        foreach ($c as $index => $value) {
            unset($manyToOne[$index]);
        }

        return array_unique(array_values(array_merge($manyToOne, $self, $oneToMany, $manyToMany)));
    }

    public static function getPrimaryKeys(): FieldCollection
    {
        if (static::$primaryKeys) {
            return static::$primaryKeys;
        }

        return static::$primaryKeys = static::getFields()
            ->filter(function (Field $field) {
                return $field->is(PrimaryKey::class);
            });
    }

    public static function createWrittenEvent(array $identifiers, Context $context, array $errors): ?WrittenEvent
    {
        if (!array_key_exists(static::class, $identifiers)) {
            return null;
        }

        $ids = $identifiers[static::class];
        $class = self::getWrittenEventClass();

        return new $class($ids, $context, $errors);
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        if (!$existence->exists() && static::getFields()->has('createdAt')) {
            return [
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];
        }
        if ($existence->exists() && static::getFields()->has('updatedAt')) {
            return [
                'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];
        }

        return [];
    }

    public static function getDetailStructClass(): string
    {
        return static::getBasicStructClass();
    }

    public static function getDetailCollectionClass(): string
    {
        return static::getBasicCollectionClass();
    }

    public static function getParentPropertyName(): ?string
    {
        return null;
    }

    public static function isChildrenAware(): bool
    {
        return static::getFields()->get('children') instanceof ChildrenAssociationField;
    }

    public static function isChildCountAware(): bool
    {
        return static::getFields()->get('childCount') instanceof ChildCountField;
    }

    public static function isInheritanceAware(): bool
    {
        return static::getFields()->get('parent') instanceof ManyToOneAssociationField;
    }

    public static function isVersionAware(): bool
    {
        return static::getFields()->has('versionId');
    }

    public static function isCatalogAware(): bool
    {
        return static::getFields()->has('catalogId');
    }

    public static function isTenantAware(): bool
    {
        return static::getFields()->has('tenantId');
    }

    protected static function filterAssociationReferences(string $type, FieldCollection $fields)
    {
        $associations = $fields->filterInstance($type)->getElements();

        $associations = array_map(function (AssociationInterface $association) {
            if ($association->getReferenceClass() !== static::class) {
                return $association->getReferenceClass();
            }

            return null;
        }, $associations);

        return array_filter($associations);
    }
}
