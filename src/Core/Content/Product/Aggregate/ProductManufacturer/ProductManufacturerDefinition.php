<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
    }


    public static function getBasicCollectionClass(): string
    {
        return ProductManufacturerBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductManufacturerBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ProductManufacturerTranslationDefinition::class;
    }

}
