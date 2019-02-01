<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation\NavigationTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class NavigationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'navigation';
    }

    public static function getCollectionClass(): string
    {
        return NavigationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NavigationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),

            new FkField('category_id', 'categoryId', CategoryDefinition::class),
            new ReferenceVersionField(CategoryDefinition::class, 'category_version_id'),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, false),

            new TranslatedField('name'),

            new TreeLevelField('level', 'level'),
            new TreePathField('path', 'path'),
            new ChildCountField(),

            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslationsAssociationField(NavigationTranslationDefinition::class, 'navigation_id'))->addFlags(new Required()),
            new ChildrenAssociationField(self::class),
            new ParentAssociationField(self::class, false),
        ]);
    }
}
