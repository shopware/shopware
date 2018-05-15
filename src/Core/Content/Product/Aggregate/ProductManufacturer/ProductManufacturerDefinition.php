<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Content\Media\Definition\MediaDefinition;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerDetailCollection;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Event\ProductManufacturerDeletedEvent;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Event\ProductManufacturerWrittenEvent;

use Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerDetailStruct;

class ProductManufacturerDefinition extends EntityDefinition
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
        return 'product_manufacturer';
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

            new FkField('media_id', 'mediaId', MediaDefinition::class),
            new ReferenceVersionField(MediaDefinition::class),

            new StringField('link', 'link'),
            new DateField('updated_at', 'updatedAt'),
            new DateField('created_at', 'createdAt'),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new TranslatedField(new StringField('meta_title', 'metaTitle')),
            new TranslatedField(new StringField('meta_description', 'metaDescription')),
            new TranslatedField(new StringField('meta_keywords', 'metaKeywords')),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'manufacturer', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new TranslationsAssociationField('translations', ProductManufacturerTranslationDefinition::class, 'product_manufacturer_id', false, 'id'))->setFlags(new CascadeDelete(), new Required()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductManufacturerRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductManufacturerBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductManufacturerDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductManufacturerWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductManufacturerBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ProductManufacturerTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ProductManufacturerDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductManufacturerDetailCollection::class;
    }
}
