<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamAssignment\ProductStreamAssignmentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamTab\ProductStreamTabDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVariation\ProductVariationDefinition;
use Shopware\Core\Content\Product\ORM\Field\ProductCoverField;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ListField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\PriceField;
use Shopware\Core\Framework\ORM\Field\PriceRulesJsonField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\Inherited;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Unit\UnitDefinition;

class ProductDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product';
    }

    public static function isInheritanceAware(): bool
    {
        return true;
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            new FkField('parent_id', 'parentId', self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),

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
            (new PriceRulesJsonField('listing_prices', 'listingPrices'))->setFlags(new Inherited()),
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
            (new ListField('category_tree', 'categoryTree', IdField::class))->setFlags(new Inherited()),
            (new ListField('datasheet_ids', 'datasheetIds', IdField::class))->setFlags(new Inherited()),
            new ListField('variation_ids', 'variationIds', IdField::class),

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
            (new OneToManyAssociationField('priceRules', ProductPriceRuleDefinition::class, 'product_id', true))->setFlags(new CascadeDelete(), new Inherited()),
            (new OneToManyAssociationField('services', ProductServiceDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('datasheet', ConfigurationGroupOptionDefinition::class, ProductDatasheetDefinition::class, false, 'product_id', 'configuration_group_option_id'))->setFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete(), new Inherited()),

            //not inherited associations
            (new ManyToManyAssociationField('seoCategories', CategoryDefinition::class, ProductSeoCategoryDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('tabs', ProductStreamDefinition::class, ProductStreamTabDefinition::class, false, 'product_id', 'product_stream_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('streams', ProductStreamDefinition::class, ProductStreamAssignmentDefinition::class, false, 'product_id', 'product_stream_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, ProductCategoryTreeDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('searchKeywords', ProductSearchKeywordDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new TranslationsAssociationField('translations', ProductTranslationDefinition::class, 'product_id', false, 'id'))->setFlags(new Inherited(), new CascadeDelete(), new Required(), new WriteOnly()),
            (new ProductCoverField('cover', true))->setFlags(new ReadOnly()),

            (new OneToManyAssociationField('configurators', ProductConfiguratorDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('variations', ConfigurationGroupOptionDefinition::class, ProductVariationDefinition::class, false, 'product_id', 'configuration_group_option_id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ProductCollection::class;
    }

    public static function getStructClass(): string
    {
        return ProductStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ProductTranslationDefinition::class;
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
