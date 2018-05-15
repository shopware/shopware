<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\ContextPricesJsonField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\JsonArrayField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\LongTextWithHtmlField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\PriceField;
use Shopware\Api\Entity\Field\ProductCoverField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductDetailCollection;
use Shopware\Api\Product\Event\Product\ProductDeletedEvent;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Product\Struct\ProductDetailStruct;
use Shopware\System\Tax\Definition\TaxDefinition;
use Shopware\System\Unit\Definition\UnitDefinition;

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

    public static function getParentPropertyName(): string
    {
        return 'parent';
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
            new ReferenceVersionField(self::class, 'parent_version_id'),

            //not inherited fields
            new BoolField('active', 'active'),
            new IntField('stock', 'stock'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),

            //inherited foreign keys with version fields
            (new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class))->setFlags(new Inherited(), new Required()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->setFlags(new Inherited(), new Required()),

            (new FkField('unit_id', 'unitId', UnitDefinition::class))->setFlags(new Inherited()),
            new ReferenceVersionField(UnitDefinition::class),

            (new FkField('tax_id', 'taxId', TaxDefinition::class))->setFlags(new Inherited(), new Required()),
            (new ReferenceVersionField(TaxDefinition::class))->setFlags(new Inherited(), new Required()),

            //inherited data fields
            (new PriceField('price', 'price'))->setFlags(new Inherited(), new Required()),
            (new ContextPricesJsonField('listing_prices', 'listingPrices'))->setFlags(new Inherited()),
            (new StringField('supplier_number', 'supplierNumber'))->setFlags(new Inherited(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new StringField('ean', 'ean'))->setFlags(new Inherited(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new BoolField('is_closeout', 'isCloseout'))->setFlags(new Inherited()),
            (new IntField('min_stock', 'minStock'))->setFlags(new Inherited()),
            (new IntField('purchase_steps', 'purchaseSteps'))->setFlags(new Inherited()),
            (new IntField('max_purchase', 'maxPurchase'))->setFlags(new Inherited()),
            (new IntField('min_purchase', 'minPurchase'))->setFlags(new Inherited()),
            (new FloatField('purchase_unit', 'purchaseUnit'))->setFlags(new Inherited()),
            (new FloatField('reference_unit', 'referenceUnit'))->setFlags(new Inherited()),
            (new BoolField('shipping_free', 'shippingFree'))->setFlags(new Inherited()),
            (new FloatField('purchase_price', 'purchasePrice'))->setFlags(new Inherited()),
            (new IntField('pseudo_sales', 'pseudoSales'))->setFlags(new Inherited()),
            (new BoolField('mark_as_topseller', 'markAsTopseller'))->setFlags(new Inherited()),
            (new IntField('sales', 'sales'))->setFlags(new Inherited()),
            (new IntField('position', 'position'))->setFlags(new Inherited()),
            (new FloatField('weight', 'weight'))->setFlags(new Inherited()),
            (new FloatField('width', 'width'))->setFlags(new Inherited()),
            (new FloatField('height', 'height'))->setFlags(new Inherited()),
            (new FloatField('length', 'length'))->setFlags(new Inherited()),
            (new StringField('template', 'template'))->setFlags(new Inherited()),
            (new BoolField('allow_notification', 'allowNotification'))->setFlags(new Inherited()),
            (new DateField('release_date', 'releaseDate'))->setFlags(new Inherited()),
            (new JsonArrayField('category_tree', 'categoryTree'))->setFlags(new Inherited()),
            (new JsonArrayField('datasheet_ids', 'datasheetIds'))->setFlags(new Inherited()),
            new JsonArrayField('variation_ids', 'variationIds'),

            (new IntField('min_delivery_time', 'minDeliveryTime'))->setFlags(new Inherited()),
            (new IntField('max_delivery_time', 'maxDeliveryTime'))->setFlags(new Inherited()),
            (new IntField('restock_time', 'restockTime'))->setFlags(new Inherited()),

            (new TranslatedField(new StringField('additional_text', 'additionalText')))->setFlags(new Inherited()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Inherited(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField(new LongTextField('keywords', 'keywords')))->setFlags(new Inherited(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new Inherited()),
            (new TranslatedField(new LongTextWithHtmlField('description_long', 'descriptionLong')))->setFlags(new Inherited()),
            (new TranslatedField(new StringField('meta_title', 'metaTitle')))->setFlags(new Inherited()),
            (new TranslatedField(new StringField('pack_unit', 'packUnit')))->setFlags(new Inherited()),

            //parent - child inheritance
            (new ManyToOneAssociationField('parent', 'parent_id', self::class, false))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),

            //inherited associations
            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, true, 'id'))->setFlags(new Inherited()),

            (new ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, true, 'id'))->setFlags(new Inherited(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, true, 'id'))->setFlags(new Inherited()),
            (new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id', false))->setFlags(new CascadeDelete(), new Inherited()),
            (new OneToManyAssociationField('contextPrices', ProductContextPriceDefinition::class, 'product_id', true))->setFlags(new CascadeDelete(), new Inherited()),
            (new OneToManyAssociationField('services', ProductServiceDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('datasheet', ConfigurationGroupOptionDefinition::class, ProductDatasheetDefinition::class, false, 'product_id', 'configuration_group_option_id'))->setFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete(), new Inherited()),

            //not inherited associations
            (new ManyToManyAssociationField('seoCategories', CategoryDefinition::class, ProductSeoCategoryDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('tabs', ProductStreamDefinition::class, ProductStreamTabDefinition::class, false, 'product_id', 'product_stream_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('streams', ProductStreamDefinition::class, ProductStreamAssignmentDefinition::class, false, 'product_id', 'product_stream_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, ProductCategoryTreeDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('searchKeywords', ProductSearchKeywordDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING), new WriteOnly()),
            (new TranslationsAssociationField('translations', ProductTranslationDefinition::class, 'product_id', false, 'id'))->setFlags(new Inherited(), new CascadeDelete(), new Required(), new WriteOnly()),
            (new ProductCoverField('cover', true))->setFlags(new ReadOnly()),

            (new OneToManyAssociationField('configurators', ProductConfiguratorDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('variations', ConfigurationGroupOptionDefinition::class, ProductVariationDefinition::class, false, 'product_id', 'configuration_group_option_id'))->setFlags(new CascadeDelete())
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

    public static function getDefaults(EntityExistence $existence): array
    {
        if ($existence->exists()) {
            return [];
        }
        if ($existence->isChild()) {
            return [];
        }

        return [
            'minPurchase' => 1,
            'isCloseout' => false,
            'purchaseSteps' => 1,
            'shippingFree' => false,
            'sales' => 0,
            'restockTime' => 1,
            'minDeliveryTime' => 1,
            'maxDeliveryTime' => 2,
        ];
    }
}
