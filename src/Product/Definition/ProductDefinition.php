<?php declare(strict_types=1);

namespace Shopware\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\LongTextWithHtmlField;
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
use Shopware\Category\Definition\CategoryDefinition;
use Shopware\Product\Collection\ProductBasicCollection;
use Shopware\Product\Collection\ProductDetailCollection;
use Shopware\Product\Event\Product\ProductWrittenEvent;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\Tax\Definition\TaxDefinition;
use Shopware\Unit\Definition\UnitDefinition;

class ProductDefinition extends EntityDefinition
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
        return 'product';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('tax_uuid', 'taxUuid', TaxDefinition::class),
            new FkField('product_manufacturer_uuid', 'manufacturerUuid', ProductManufacturerDefinition::class),
            new FkField('unit_uuid', 'unitUuid', UnitDefinition::class),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new StringField('container_uuid', 'containerUuid'),
            new BoolField('is_main', 'isMain'),
            new BoolField('active', 'active'),
            new StringField('price_group_uuid', 'priceGroupUuid'),
            new StringField('supplier_number', 'supplierNumber'),
            new StringField('ean', 'ean'),
            new IntField('stock', 'stock'),
            new BoolField('is_closeout', 'isCloseout'),
            new IntField('min_stock', 'minStock'),
            new IntField('purchase_steps', 'purchaseSteps'),
            new IntField('max_purchase', 'maxPurchase'),
            new IntField('min_purchase', 'minPurchase'),
            new FloatField('purchase_unit', 'purchaseUnit'),
            new FloatField('reference_unit', 'referenceUnit'),
            new BoolField('shipping_free', 'shippingFree'),
            new FloatField('purchase_price', 'purchasePrice'),
            new IntField('pseudo_sales', 'pseudoSales'),
            new BoolField('mark_as_topseller', 'markAsTopseller'),
            new IntField('sales', 'sales'),
            new IntField('position', 'position'),
            new FloatField('weight', 'weight'),
            new FloatField('width', 'width'),
            new FloatField('height', 'height'),
            new FloatField('length', 'length'),
            new StringField('template', 'template'),
            new BoolField('allow_notification', 'allowNotification'),
            new DateField('release_date', 'releaseDate'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new TranslatedField(new StringField('additional_text', 'additionalText')),
            new TranslatedField(new LongTextField('keywords', 'keywords')),
            new TranslatedField(new LongTextField('description', 'description')),
            new TranslatedField(new LongTextWithHtmlField('description_long', 'descriptionLong')),
            new TranslatedField(new StringField('meta_title', 'metaTitle')),
            new TranslatedField(new StringField('pack_unit', 'packUnit')),
            new ManyToOneAssociationField('tax', 'tax_uuid', TaxDefinition::class, true),
            new ManyToOneAssociationField('manufacturer', 'product_manufacturer_uuid', ProductManufacturerDefinition::class, true),
            new ManyToOneAssociationField('unit', 'unit_uuid', UnitDefinition::class, true),
            new OneToManyAssociationField('listingPrices', ProductListingPriceDefinition::class, 'product_uuid', true, 'uuid'),
            new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_uuid', false, 'uuid'),
            new OneToManyAssociationField('prices', ProductPriceDefinition::class, 'product_uuid', true, 'uuid'),
            (new TranslationsAssociationField('translations', ProductTranslationDefinition::class, 'product_uuid', false, 'uuid'))->setFlags(new Required()),
            new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, false, 'product_uuid', 'category_uuid', 'categoryUuids'),
            new ManyToManyAssociationField('categoryTree', CategoryDefinition::class, ProductCategoryTreeDefinition::class, false, 'product_uuid', 'category_uuid', 'categoryTreeUuids'),
            new ManyToManyAssociationField('seoCategories', CategoryDefinition::class, ProductSeoCategoryDefinition::class, false, 'product_uuid', 'category_uuid', 'seoCategoryUuids'),
            new ManyToManyAssociationField('tabs', ProductStreamDefinition::class, ProductStreamTabDefinition::class, false, 'product_uuid', 'product_stream_uuid', 'tabUuids'),
            new ManyToManyAssociationField('streams', ProductStreamDefinition::class, ProductStreamAssignmentDefinition::class, false, 'product_uuid', 'product_stream_uuid', 'streamUuids'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ProductTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ProductDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductDetailCollection::class;
    }
}
