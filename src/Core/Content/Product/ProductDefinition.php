<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVariation\ProductVariationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceRulesJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Unit\UnitDefinition;

class ProductDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product';
    }

    public static function useKeywordSearch(): bool
    {
        return true;
    }

    public static function isInheritanceAware(): bool
    {
        return true;
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            new ParentFkField(self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),

            (new BlacklistRuleField())->setFlags(new Inherited()),
            (new WhitelistRuleField())->setFlags(new Inherited()),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),

            //not inherited fields
            new BoolField('active', 'active'),
            new IntField('stock', 'stock'),
            new CreatedAtField(),
            new UpdatedAtField(),

            //inherited foreign keys with version fields
            (new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class))->setFlags(new Inherited(), new Required()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->setFlags(new Inherited(), new Required()),

            (new FkField('unit_id', 'unitId', UnitDefinition::class))->setFlags(new Inherited()),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->setFlags(new Inherited(), new Required()),

            (new FkField('product_media_id', 'coverId', ProductMediaDefinition::class))->setFlags(new Inherited()),
            (new ReferenceVersionField(ProductMediaDefinition::class))->setFlags(new Inherited()),

            //inherited data fields
            (new PriceField('price', 'price'))->setFlags(new Inherited(), new Required()),
            (new PriceRulesJsonField('listing_prices', 'listingPrices'))->setFlags(new Inherited(), new ReadOnly()),

            (new StringField('manufacturer_number', 'manufacturerNumber'))->setFlags(new Inherited(), new SearchRanking(self::LOW_SEARCH_RAKING)),
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
            (new BoolField('mark_as_topseller', 'markAsTopseller'))->setFlags(new Inherited()),
            (new IntField('sales', 'sales'))->setFlags(new Inherited()),
            (new IntField('position', 'position'))->setFlags(new Inherited()),
            (new FloatField('weight', 'weight'))->setFlags(new Inherited()),
            (new FloatField('width', 'width'))->setFlags(new Inherited()),
            (new FloatField('height', 'height'))->setFlags(new Inherited()),
            (new FloatField('length', 'length'))->setFlags(new Inherited()),
            (new BoolField('allow_notification', 'allowNotification'))->setFlags(new Inherited()),
            (new DateField('release_date', 'releaseDate'))->setFlags(new Inherited()),
            (new ListField('category_tree', 'categoryTree', IdField::class))->setFlags(new Inherited(), new ReadOnly()),
            (new ListField('datasheet_ids', 'datasheetIds', IdField::class))->setFlags(new Inherited()),
            new ListField('variation_ids', 'variationIds', IdField::class),
            (new IntField('min_delivery_time', 'minDeliveryTime'))->setFlags(new Inherited()),
            (new IntField('max_delivery_time', 'maxDeliveryTime'))->setFlags(new Inherited()),
            (new IntField('restock_time', 'restockTime'))->setFlags(new Inherited()),

            //translatable fields
            (new TranslatedField('additionalText'))->setFlags(new Inherited()),
            (new TranslatedField('name'))->setFlags(new Inherited(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField('keywords'))->setFlags(new Inherited(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('description'))->setFlags(new Inherited()),
            (new TranslatedField('descriptionLong'))->setFlags(new Inherited()),
            (new TranslatedField('metaTitle'))->setFlags(new Inherited()),
            (new TranslatedField('packUnit'))->setFlags(new Inherited()),

            //parent - child inheritance
            new ParentAssociationField(self::class, false),
            new ChildrenAssociationField(self::class),

            //inherited associations and associations which are loaded immediately
            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, true, 'id'))->setFlags(new Inherited()),
            (new ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, true, 'id'))->setFlags(new Inherited(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, true, 'id'))->setFlags(new Inherited()),
            (new ManyToOneAssociationField('cover', 'product_media_id', ProductMediaDefinition::class, true, 'id'))->setFlags(new Inherited()),
            (new OneToManyAssociationField('priceRules', ProductPriceRuleDefinition::class, 'product_id', true))->setFlags(new CascadeDelete(), new Inherited()),

            //inherited associations which are not loaded immediately
            (new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id', false))->setFlags(new CascadeDelete(), new Inherited()),
            (new OneToManyAssociationField('services', ProductServiceDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete(), new Inherited()),

            //associations which are not loaded immediately
            (new ManyToManyAssociationField('datasheet', ConfigurationGroupOptionDefinition::class, ProductDatasheetDefinition::class, false, 'product_id', 'configuration_group_option_id'))->setFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete(), new Inherited()),

            //association for special keyword mapping for search algorithm
            new SearchKeywordAssociationField(),

            //not inherited associations
            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, ProductCategoryTreeDefinition::class, false, 'product_id', 'category_id'))->setFlags(new CascadeDelete(), new ReadOnly()),
            (new TranslationsAssociationField(ProductTranslationDefinition::class))->setFlags(new Inherited(), new CascadeDelete(), new Required()),

            (new OneToManyAssociationField('configurators', ProductConfiguratorDefinition::class, 'product_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('variations', ConfigurationGroupOptionDefinition::class, ProductVariationDefinition::class, false, 'product_id', 'configuration_group_option_id'))->setFlags(new CascadeDelete()),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ProductCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductEntity::class;
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
