<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Struct\ProductManufacturerTranslationBasicStruct;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class ProductManufacturerTranslationDefinition extends EntityDefinition
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
        return 'product_manufacturer_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('product_manufacturer_id', 'productManufacturerId', ProductManufacturerDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CatalogField(),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            new StringField('meta_title', 'metaTitle'),
            new StringField('meta_description', 'metaDescription'),
            new StringField('meta_keywords', 'metaKeywords'),
            new ManyToOneAssociationField('productManufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }


    public static function getBasicCollectionClass(): string
    {
        return ProductManufacturerTranslationBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductManufacturerTranslationBasicStruct::class;
    }

}
