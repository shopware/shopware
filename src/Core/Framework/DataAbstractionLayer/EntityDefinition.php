<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Struct\ArrayEntity;

abstract class EntityDefinition
{
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

    /**
     * @var (string|null)[]
     */
    protected static $translationDefinitions = [];

    /**
     * @var bool[]
     */
    protected static $keywordSearchDefinitions = [];

    /**
     * @var Field[][]
     */
    protected static $primaryKeys = [];

    public static function addExtension(EntityExtensionInterface $extension): void
    {
        static::$extensions[static::class][\get_class($extension)] = $extension;
        static::$fields[static::class] = null;
    }

    abstract public static function getEntityName(): string;

    public static function useKeywordSearch(): bool
    {
        if (isset(static::$keywordSearchDefinitions[static::class])) {
            return static::$keywordSearchDefinitions[static::class];
        }

        foreach (static::getFields() as $field) {
            if ($field instanceof SearchKeywordAssociationField) {
                return static::$keywordSearchDefinitions[static::class] = true;
            }
        }

        return static::$keywordSearchDefinitions[static::class] = false;
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
            $new = new FieldCollection();

            $extension->extendFields($new);

            /** @var Field $field */
            foreach ($new as $field) {
                $field->addFlags(new Extension());
                $fields->add($field);
            }
        }

        static::$fields[static::class] = $fields;

        return static::$fields[static::class];
    }

    public static function getCollectionClass(): string
    {
        return EntityCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ArrayEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return null;
    }

    /**
     * @return string|EntityDefinition|null
     */
    public static function getTranslationDefinitionClass(): ?string
    {
        if (array_key_exists(static::class, static::$translationDefinitions)) {
            return static::$translationDefinitions[static::class];
        }
        static::$translationDefinitions[static::class] = null;

        foreach (static::getFields() as $field) {
            if ($field instanceof TranslationsAssociationField) {
                static::$translationDefinitions[static::class] = $field->getReferenceClass();
                break;
            }
        }

        return static::$translationDefinitions[static::class];
    }

    public static function getPrimaryKeys(): array
    {
        if (array_key_exists(static::class, static::$primaryKeys)) {
            return static::$primaryKeys[static::class];
        }

        $fields = array_filter(static::getFields()->getElements(), function (Field $field) {
            return $field->is(PrimaryKey::class);
        });

        return static::$primaryKeys[static::class] = $fields;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [];
    }

    public static function isChildrenAware(): bool
    {
        return static::getFields()->filterInstance(ChildrenAssociationField::class)->count() > 0;
    }

    public static function isChildCountAware(): bool
    {
        return static::getFields()->get('childCount') instanceof ChildCountField;
    }

    public static function isParentAware(): bool
    {
        return static::getFields()->get('parent') instanceof ParentAssociationField;
    }

    public static function isInheritanceAware(): bool
    {
        return false;
    }

    public static function isVersionAware(): bool
    {
        return static::getFields()->has('versionId');
    }

    public static function getDeleteProtectionKey(): ?string
    {
        return null;
    }

    public static function isBlacklistAware(): bool
    {
        return static::getFields()->has('blacklistIds');
    }

    public static function isWhitelistAware(): bool
    {
        return static::getFields()->has('whitelistIds');
    }

    public static function isTreeAware(): bool
    {
        return
            static::isParentAware() &&
            (static::getFields()->filterInstance(TreePathField::class)->count() > 0 ||
            static::getFields()->filterInstance(TreeLevelField::class)->count() > 0)

        ;
    }

    abstract protected static function defineFields(): FieldCollection;
}
