<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\ChildCountField;
use Shopware\Core\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Struct\ArrayStruct;

abstract class EntityDefinition
{
    public const HIGH_SEARCH_RANKING = 500;
    public const MIDDLE_SEARCH_RANKING = 250;
    public const LOW_SEARCH_RAKING = 80;
    public const ASSOCIATION_SEARCH_RANKING = 0.25;

    /**
     * @var (FieldCollection|null)[]
     */
    protected static $fields = [];

    /**
     * @var Field[][]
     */
    protected static $searchFields = [];

    /**
     * @var EntityExtensionInterface[][]
     */
    protected static $extensions = [];

    public static function addExtension(EntityExtensionInterface $extension): void
    {
        static::$extensions[static::class][get_class($extension)] = $extension;
        static::$fields[static::class] = null;
    }

    abstract public static function getEntityName(): string;

    public static function getEntityNameByDefinition(string $definition): string
    {
        return $definition::getEntityName();
    }

    public static function useKeywordSearch(): bool
    {
        return false;
    }

    /**
     * @return Field[]
     */
    public static function getSearchFields(): array
    {
        if (isset(static::$searchFields[static::class])) {
            return static::$searchFields[static::class];
        }

        $fields = static::getFields()->fmap(
            function (Field $field) {
                if ($field->is(SearchRanking::class)) {
                    return $field;
                }

                return null;
            }
        );

        return static::$searchFields[static::class] = $fields;
    }

    public static function getFields(): FieldCollection
    {
        if (isset(static::$fields[static::class])) {
            return static::$fields[static::class];
        }

        $fields = static::defineFields();

        $extensions = static::$extensions[static::class] ?? [];
        foreach ($extensions as $extension) {
            $extension->extendFields($fields);
        }

        static::$fields[static::class] = $fields;

        return static::$fields[static::class];
    }

    public static function getCollectionClass(): string
    {
        return EntityCollection::class;
    }

    public static function getStructClass(): string
    {
        return ArrayStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

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
        return static::getFields()
            ->filter(function (Field $field) {
                return $field->is(PrimaryKey::class);
            });
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [];
    }

    public static function allowInheritance(): bool
    {
        return false;
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
        return static::allowInheritance() && static::getFields()->get('parent') instanceof ManyToOneAssociationField;
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

    abstract protected static function defineFields(): FieldCollection;

    protected static function filterAssociationReferences(string $type, FieldCollection $fields): array
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
