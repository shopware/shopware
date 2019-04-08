<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
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
use Shopware\Core\Framework\Tag\TagDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
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
            'purchaseSteps' => 1,
            'shippingFree' => false,
            'restockTime' => 1,
            'minDeliveryTime' => 1,
            'maxDeliveryTime' => 2,
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),

            new BlacklistRuleField(),
            new WhitelistRuleField(),

            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),

            //not inherited fields
            new BoolField('active', 'active'),
            (new IntField('stock', 'stock'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),

            //inherited foreign keys with version fields
            (new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class))->addFlags(new Inherited(), new Required()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->addFlags(new Inherited(), new Required()),

            (new FkField('unit_id', 'unitId', UnitDefinition::class))->addFlags(new Inherited()),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new Inherited(), new Required()),

            (new FkField('product_media_id', 'coverId', ProductMediaDefinition::class))->addFlags(new Inherited()),
            (new ReferenceVersionField(ProductMediaDefinition::class))->addFlags(new Inherited()),

            //inherited data fields
            (new PriceField('price', 'price'))->addFlags(new Inherited(), new Required()),
            (new PriceRulesJsonField('listing_prices', 'listingPrices'))->addFlags(new Inherited()),

            (new StringField('manufacturer_number', 'manufacturerNumber'))->addFlags(new Inherited()),
            (new StringField('ean', 'ean'))->addFlags(new Inherited(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new NumberRangeField('product_number', 'productNumber'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new BoolField('is_closeout', 'isCloseout'))->addFlags(new Inherited()),
            (new IntField('purchase_steps', 'purchaseSteps'))->addFlags(new Inherited()),
            (new IntField('max_purchase', 'maxPurchase'))->addFlags(new Inherited()),
            (new IntField('min_purchase', 'minPurchase'))->addFlags(new Inherited()),
            (new FloatField('purchase_unit', 'purchaseUnit'))->addFlags(new Inherited()),
            (new FloatField('reference_unit', 'referenceUnit'))->addFlags(new Inherited()),
            (new BoolField('shipping_free', 'shippingFree'))->addFlags(new Inherited()),
            (new FloatField('purchase_price', 'purchasePrice'))->addFlags(new Inherited()),
            (new BoolField('mark_as_topseller', 'markAsTopseller'))->addFlags(new Inherited()),
            (new FloatField('weight', 'weight'))->addFlags(new Inherited()),
            (new FloatField('width', 'width'))->addFlags(new Inherited()),
            (new FloatField('height', 'height'))->addFlags(new Inherited()),
            (new FloatField('length', 'length'))->addFlags(new Inherited()),
            (new DateField('release_date', 'releaseDate'))->addFlags(new Inherited()),
            (new ListField('category_tree', 'categoryTree', IdField::class))->addFlags(new Inherited(), new WriteProtected()),
            (new ListField('datasheet_ids', 'datasheetIds', IdField::class))->addFlags(new Inherited()),
            new ListField('option_ids', 'optionIds', IdField::class),
            (new IntField('min_delivery_time', 'minDeliveryTime'))->addFlags(new Inherited()),
            (new IntField('max_delivery_time', 'maxDeliveryTime'))->addFlags(new Inherited()),
            (new IntField('restock_time', 'restockTime'))->addFlags(new Inherited()),
            (new ListField('tag_ids', 'tagIds', IdField::class))->addFlags(new Inherited()),

            //translatable fields
            (new TranslatedField('additionalText'))->addFlags(new Inherited()),
            (new TranslatedField('name'))->addFlags(new Inherited(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('keywords'))->addFlags(new Inherited()),
            (new TranslatedField('description'))->addFlags(new Inherited()),
            (new TranslatedField('metaTitle'))->addFlags(new Inherited()),
            (new TranslatedField('packUnit'))->addFlags(new Inherited()),
            new TranslatedField('attributes'),

            //parent - child inheritance
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),

            //inherited associations and associations which are loaded immediately
            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, 'id', true))->addFlags(new Inherited()),
            (new ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, 'id', true))->addFlags(new Inherited()),
            (new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, 'id', true))->addFlags(new Inherited()),
            (new ManyToOneAssociationField('cover', 'product_media_id', ProductMediaDefinition::class, 'id', true))->addFlags(new Inherited()),
            (new OneToManyAssociationField('prices', ProductPriceDefinition::class, 'product_id'))->addFlags(new CascadeDelete(), new Inherited()),

            //inherited associations which are not loaded immediately
            (new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id'))->addFlags(new CascadeDelete(), new Inherited()),

            //associations which are not loaded immediately
            (new ManyToManyAssociationField('datasheet', ConfigurationGroupOptionDefinition::class, ProductDatasheetDefinition::class, 'product_id', 'configuration_group_option_id'))->addFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id'))->addFlags(new CascadeDelete(), new Inherited()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, ProductTagDefinition::class, 'product_id', 'tag_id'))->addFlags(new Inherited()),

            //association for special keyword mapping for search algorithm
            new SearchKeywordAssociationField(),

            //not inherited associations
            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, ProductCategoryTreeDefinition::class, 'product_id', 'category_id'))->addFlags(new CascadeDelete(), new WriteProtected()),
            (new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id'))->addFlags(new Inherited(), new Required()),

            (new OneToManyAssociationField('configuratorSettings', ProductConfiguratorSettingDefinition::class, 'product_id', 'id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('options', ConfigurationGroupOptionDefinition::class, ProductOptionDefinition::class, 'product_id', 'configuration_group_option_id'))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('visibilities', ProductVisibilityDefinition::class, 'product_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
