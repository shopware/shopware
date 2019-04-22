<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Navigation\NavigationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
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
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\System\Tag\TagDefinition;

class CategoryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'category';
    }

    public static function getSalesChannelDecorationDefinition(): string
    {
        return SalesChannelCategoryDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return CategoryCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CategoryEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        $defaults = parent::getDefaults($existence);
        $defaults['displayNestedProducts'] = true;
        $defaults['active'] = false;

        return $defaults;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),

            new FkField('after_category_id', 'afterCategoryId', self::class),
            (new ReferenceVersionField(self::class, 'after_category_version_id'))->addFlags(new Required()),

            new FkField('media_id', 'mediaId', MediaDefinition::class),

            (new BoolField('display_nested_products', 'displayNestedProducts'))->addFlags(new Required()),
            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),
            new TreePathField('path', 'path'),
            new TreeLevelField('level', 'level'),
            new BoolField('active', 'active'),
            new ChildCountField(),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('attributes'),

            new ParentAssociationField(self::class, 'id'),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            new ChildrenAssociationField(self::class),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'category_id'))->addFlags(new Required()),

            (new OneToManyAssociationField('navigations', NavigationDefinition::class, 'category_id'))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductCategoryDefinition::class, 'category_id', 'product_id'))->addFlags(new CascadeDelete(), new ReverseInherited('categories')),
            (new ManyToManyAssociationField('nestedProducts', ProductDefinition::class, ProductCategoryTreeDefinition::class, 'category_id', 'product_id'))->addFlags(new CascadeDelete(), new WriteProtected()),
            new ManyToManyAssociationField('tags', TagDefinition::class, CategoryTagDefinition::class, 'category_id', 'tag_id'),
        ]);
    }
}
