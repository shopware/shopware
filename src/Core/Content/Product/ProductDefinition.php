<?php declare(strict_types=1);

namespace Shopware\Content\Product;

use Shopware\Content\Category\CategoryDefinition;
use Shopware\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;
use Shopware\Content\Product\Aggregate\ProductContextPrice\ProductContextPriceDefinition;
use Shopware\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Content\Product\Aggregate\ProductStreamAssignment\ProductStreamAssignmentDefinition;
use Shopware\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;
use Shopware\Content\Product\Aggregate\ProductStreamTab\ProductStreamTabDefinition;
use Shopware\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Content\Product\Aggregate\ProductVariation\ProductVariationDefinition;
use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\ContextPricesJsonField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\JsonArrayField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\LongTextWithHtmlField;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\PriceField;
use Shopware\Framework\ORM\Field\ProductCoverField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\EntityExistence;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\Inherited;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Content\Product\Collection\ProductBasicCollection;
use Shopware\Content\Product\Collection\ProductDetailCollection;
use Shopware\Content\Product\Event\ProductDeletedEvent;
use Shopware\Content\Product\Event\ProductWrittenEvent;

use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Content\Product\Struct\ProductDetailStruct;
use Shopware\System\Tax\TaxDefinition;
use Shopware\System\Unit\UnitDefinition;

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
