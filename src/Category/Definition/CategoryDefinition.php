<?php declare(strict_types=1);

namespace Shopware\Category\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Category\Collection\CategoryBasicCollection;
use Shopware\Category\Collection\CategoryDetailCollection;
use Shopware\Category\Event\Category\CategoryWrittenEvent;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Category\Struct\CategoryDetailStruct;
use Shopware\Media\Definition\MediaDefinition;
use Shopware\Product\Definition\ProductCategoryDefinition;
use Shopware\Product\Definition\ProductCategoryTreeDefinition;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Product\Definition\ProductSeoCategoryDefinition;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_uuid', 'parentUuid', self::class),
            new FkField('media_uuid', 'mediaUuid', MediaDefinition::class),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
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
            new StringField('product_stream_uuid', 'productStreamUuid'),
            new BoolField('hide_sortings', 'hideSortings'),
            new LongTextField('sorting_uuids', 'sortingUuids'),
            new LongTextField('facet_uuids', 'facetUuids'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new TranslatedField(new LongTextField('path_names', 'pathNames')),
            new TranslatedField(new LongTextField('meta_keywords', 'metaKeywords')),
            new TranslatedField(new StringField('meta_title', 'metaTitle')),
            new TranslatedField(new LongTextField('meta_description', 'metaDescription')),
            new TranslatedField(new StringField('cms_headline', 'cmsHeadline')),
            new TranslatedField(new LongTextField('cms_description', 'cmsDescription')),
            new ManyToOneAssociationField('parent', 'parent_uuid', self::class, false),
            new ManyToOneAssociationField('media', 'media_uuid', MediaDefinition::class, true),
            (new TranslationsAssociationField('translations', CategoryTranslationDefinition::class, 'category_uuid', false, 'uuid'))->setFlags(new Required()),
            new OneToManyAssociationField('shops', ShopDefinition::class, 'category_uuid', false, 'uuid'),
            new ManyToManyAssociationField('products', ProductDefinition::class, ProductCategoryDefinition::class, false, 'category_uuid', 'product_uuid', 'productUuids'),
            new ManyToManyAssociationField('productTree', ProductDefinition::class, ProductCategoryTreeDefinition::class, false, 'category_uuid', 'product_uuid', 'productTreeUuids'),
            new ManyToManyAssociationField('seoProducts', ProductDefinition::class, ProductSeoCategoryDefinition::class, false, 'category_uuid', 'product_uuid', 'seoProductUuids'),
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
