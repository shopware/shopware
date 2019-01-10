<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class CategoryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'category';
    }

    public static function getCollectionClass(): string
    {
        return CategoryCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CategoryEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            new ParentFkField(self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),

            new FkField('media_id', 'mediaId', MediaDefinition::class),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),
            new TreePathField('path', 'path'),
            new IntField('position', 'position'),
            new TreeLevelField('level', 'level'),
            new StringField('template', 'template'),
            new BoolField('active', 'active'),
            new BoolField('is_blog', 'isBlog'),
            new StringField('external', 'external'),
            new BoolField('hide_filter', 'hideFilter'),
            new BoolField('hide_top', 'hideTop'),
            new StringField('product_box_layout', 'productBoxLayout'),
            new BoolField('hide_sortings', 'hideSortings'),
            new LongTextField('sorting_ids', 'sortingIds'),
            new LongTextField('facet_ids', 'facetIds'),
            new ChildCountField(),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslatedField('name'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('metaKeywords'))->setFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            new TranslatedField('metaTitle'),
            new TranslatedField('metaDescription'),
            new TranslatedField('cmsHeadline'),
            new TranslatedField('cmsDescription'),

            new ParentAssociationField(self::class, false),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false),
            (new ChildrenAssociationField(self::class))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductCategoryDefinition::class, false, 'category_id', 'product_id', 'id', 'id'))->setFlags(new CascadeDelete(), new ReverseInherited('categories')),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
            (new ManyToManyAssociationField('nestedProducts', ProductDefinition::class, ProductCategoryTreeDefinition::class, false, 'category_id', 'product_id'))->setFlags(new CascadeDelete(), new ReadOnly()),
        ]);
    }
}
