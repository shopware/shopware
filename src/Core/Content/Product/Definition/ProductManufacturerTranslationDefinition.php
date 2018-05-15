<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\Content\Product\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Content\Product\Collection\ProductManufacturerTranslationDetailCollection;
use Shopware\Content\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationDeletedEvent;
use Shopware\Content\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationWrittenEvent;
use Shopware\Content\Product\Repository\ProductManufacturerTranslationRepository;
use Shopware\Content\Product\Struct\ProductManufacturerTranslationBasicStruct;
use Shopware\Content\Product\Struct\ProductManufacturerTranslationDetailStruct;

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

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
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

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductManufacturerTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductManufacturerTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductManufacturerTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductManufacturerTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductManufacturerTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductManufacturerTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductManufacturerTranslationDetailCollection::class;
    }
}
