<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\ArrayField;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\LongTextWithHtmlField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductDetailCollection;
use Shopware\Api\Product\Event\Product\ProductDeletedEvent;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Product\Struct\ProductDetailStruct;
use Shopware\Api\Tax\Definition\TaxDefinition;
use Shopware\Api\Unit\Definition\UnitDefinition;

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('tax_id', 'taxId', TaxDefinition::class),
            new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class),
            new FkField('unit_id', 'unitId', UnitDefinition::class),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new BoolField('is_main', 'isMain'),
            new BoolField('active', 'active'),
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
            new IdField('container_id', 'containerId'),
            new IdField('price_group_id', 'priceGroupId'),
            new ArrayField('category_tree', 'categoryTree'),
            new TranslatedField(new StringField('additional_text', 'additionalText')),
            new TranslatedField(new LongTextField('keywords', 'keywords')),
            new TranslatedField(new LongTextField('description', 'description')),
            new TranslatedField(new LongTextWithHtmlField('description_long', 'descriptionLong')),
            new TranslatedField(new StringField('meta_title', 'metaTitle')),
            new TranslatedField(new StringField('pack_unit', 'packUnit')),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, true),
            new ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, true),
            new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, true),
            (new OneToManyAssociationField('listingPrices', ProductListingPriceDefinition::class, 'product_id', true, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('prices', ProductPriceDefinition::class, 'product_id', true, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('searchKeywords', ProductSearchKeywordDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', ProductTranslationDefinition::class, 'product_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, false, 'product_id', 'category_id', 'categoryIds'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('seoCategories', CategoryDefinition::class, ProductSeoCategoryDefinition::class, false, 'product_id', 'category_id', 'seoCategoryIds'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('tabs', ProductStreamDefinition::class, ProductStreamTabDefinition::class, false, 'product_id', 'product_stream_id', 'tabIds'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('streams', ProductStreamDefinition::class, ProductStreamAssignmentDefinition::class, false, 'product_id', 'product_stream_id', 'streamIds'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return ProductDeletedEvent::class;
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
