<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Collection\ProductManufacturerBasicCollection;
use Shopware\Api\Product\Collection\ProductManufacturerDetailCollection;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerWrittenEvent;
use Shopware\Api\Product\Repository\ProductManufacturerRepository;
use Shopware\Api\Product\Struct\ProductManufacturerBasicStruct;
use Shopware\Api\Product\Struct\ProductManufacturerDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('link', 'link'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new StringField('media_uuid', 'mediaUuid'),
            new DateField('updated_at', 'updatedAt'),
            new DateField('created_at', 'createdAt'),
            new TranslatedField(new LongTextField('description', 'description')),
            new TranslatedField(new StringField('meta_title', 'metaTitle')),
            new TranslatedField(new StringField('meta_description', 'metaDescription')),
            new TranslatedField(new StringField('meta_keywords', 'metaKeywords')),
            new OneToManyAssociationField('products', ProductDefinition::class, 'product_manufacturer_uuid', false, 'uuid'),
            (new TranslationsAssociationField('translations', ProductManufacturerTranslationDefinition::class, 'product_manufacturer_uuid', false, 'uuid'))->setFlags(new Required()),
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
