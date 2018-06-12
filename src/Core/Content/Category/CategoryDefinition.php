<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryBasicCollection;
use Shopware\Core\Content\Category\CategoryBasicStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\ChildCountField;
use Shopware\Core\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ParentField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;

class CategoryDefinition extends EntityDefinition
{
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

    public static function getEntityName(): string
    {
        return 'category';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            new FkField('parent_id', 'parentId', self::class),
            new ParentField(self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),

            new FkField('media_id', 'mediaId', MediaDefinition::class),
            new ReferenceVersionField(MediaDefinition::class),

            new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class),
            new ReferenceVersionField(ProductStreamDefinition::class),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),
            new LongTextField('path', 'path'),
            new IntField('position', 'position'),
            new IntField('level', 'level'),
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
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new TranslatedField(new LongTextField('path_names', 'pathNames')),
            (new TranslatedField(new LongTextField('meta_keywords', 'metaKeywords')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new TranslatedField(new StringField('meta_title', 'metaTitle')),
            new TranslatedField(new LongTextField('meta_description', 'metaDescription')),
            new TranslatedField(new StringField('cms_headline', 'cmsHeadline')),
            new TranslatedField(new LongTextField('cms_description', 'cmsDescription')),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class, false),
            (new ChildrenAssociationField(self::class))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', CategoryTranslationDefinition::class, 'category_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductCategoryDefinition::class, false, 'category_id', 'product_id', 'id', 'categories'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new ManyToManyAssociationField('seoProducts', ProductDefinition::class, ProductSeoCategoryDefinition::class, false, 'category_id', 'product_id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);
    }

    public static function getBasicCollectionClass(): string
    {
        return CategoryBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return CategoryBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CategoryTranslationDefinition::class;
    }
}
