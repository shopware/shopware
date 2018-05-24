<?php declare(strict_types=1);

namespace Shopware\Content\Category;

use Shopware\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Content\Category\Collection\CategoryBasicCollection;
use Shopware\Content\Category\Collection\CategoryDetailCollection;
use Shopware\Content\Category\Event\CategoryDeletedEvent;
use Shopware\Content\Category\Event\CategoryWrittenEvent;
use Shopware\Content\Category\Struct\CategoryBasicStruct;
use Shopware\Content\Category\Struct\CategoryDetailStruct;
use Shopware\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ParentField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;

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

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            new FkField('parent_id', 'parentId', self::class),
            new ParentField(self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),

            new FkField('media_id', 'mediaId', \Shopware\Content\Media\MediaDefinition::class),
            new ReferenceVersionField(\Shopware\Content\Media\MediaDefinition::class),

            new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class),
            new ReferenceVersionField(\Shopware\Content\Product\Aggregate\ProductStream\ProductStreamDefinition::class),

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
            new ManyToOneAssociationField('media', 'media_id', \Shopware\Content\Media\MediaDefinition::class, false),
            new ManyToOneAssociationField('productStream', 'product_stream_id', \Shopware\Content\Product\Aggregate\ProductStream\ProductStreamDefinition::class, false),
            (new ChildrenAssociationField(self::class))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', CategoryTranslationDefinition::class, 'category_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new ManyToManyAssociationField('products', \Shopware\Content\Product\ProductDefinition::class, ProductCategoryDefinition::class, false, 'category_id', 'product_id', 'id', 'categories'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new ManyToManyAssociationField('seoProducts', \Shopware\Content\Product\ProductDefinition::class, ProductSeoCategoryDefinition::class, false, 'category_id', 'product_id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CategoryRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CategoryBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CategoryDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CategoryWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CategoryBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CategoryTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CategoryDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CategoryDetailCollection::class;
    }
}
