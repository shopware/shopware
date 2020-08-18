<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListingPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Unit\UnitDefinition;

class ProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isInheritanceAware(): bool
    {
        return true;
    }

    public function getCollectionClass(): string
    {
        return ProductCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'isCloseout' => false,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'shippingFree' => false,
            'restockTime' => 3,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),

            (new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class))->addFlags(new Inherited()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->addFlags(new Inherited(), new Required()),

            (new FkField('unit_id', 'unitId', UnitDefinition::class))->addFlags(new Inherited()),

            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new Inherited(), new Required()),

            (new FkField('product_media_id', 'coverId', ProductMediaDefinition::class))->addFlags(new Inherited()),

            (new ReferenceVersionField(ProductMediaDefinition::class))->addFlags(new Inherited()),

            (new FkField('delivery_time_id', 'deliveryTimeId', DeliveryTimeDefinition::class))->addFlags(new Inherited()),

            (new PriceField('price', 'price'))->addFlags(new Inherited(), new Required(), new ReadProtected(SalesChannelApiSource::class)),
            (new NumberRangeField('product_number', 'productNumber'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING), new Required()),
            (new IntField('stock', 'stock'))->addFlags(new Required()),
            (new IntField('restock_time', 'restockTime'))->addFlags(new Inherited()),
            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),
            new BoolField('active', 'active'),
            (new IntField('available_stock', 'availableStock'))->addFlags(new WriteProtected()),
            (new BoolField('available', 'available'))->addFlags(new WriteProtected()),
            (new BoolField('is_closeout', 'isCloseout'))->addFlags(new Inherited()),

            (new StringField('display_group', 'displayGroup'))->addFlags(new WriteProtected()),
            (new JsonField('configurator_group_config', 'configuratorGroupConfig'))->addFlags(new ReadProtected(SalesChannelApiSource::class), new Inherited()),
            (new FkField('main_variant_id', 'mainVariantId', ProductDefinition::class)),
            (new JsonField('variant_restrictions', 'variantRestrictions'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new StringField('manufacturer_number', 'manufacturerNumber'))->addFlags(new Inherited()),
            (new StringField('ean', 'ean'))->addFlags(new Inherited(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new IntField('purchase_steps', 'purchaseSteps', 1))->addFlags(new Inherited()),
            (new IntField('max_purchase', 'maxPurchase'))->addFlags(new Inherited()),
            (new IntField('min_purchase', 'minPurchase', 1))->addFlags(new Inherited()),
            (new FloatField('purchase_unit', 'purchaseUnit'))->addFlags(new Inherited()),
            (new FloatField('reference_unit', 'referenceUnit'))->addFlags(new Inherited()),
            (new BoolField('shipping_free', 'shippingFree'))->addFlags(new Inherited()),
            (new FloatField('purchase_price', 'purchasePrice'))->addFlags(new Inherited()),
            (new BoolField('mark_as_topseller', 'markAsTopseller'))->addFlags(new Inherited()),
            (new FloatField('weight', 'weight'))->addFlags(new Inherited()),
            (new FloatField('width', 'width'))->addFlags(new Inherited()),
            (new FloatField('height', 'height'))->addFlags(new Inherited()),
            (new FloatField('length', 'length'))->addFlags(new Inherited()),
            (new DateTimeField('release_date', 'releaseDate'))->addFlags(new Inherited()),
            (new FloatField('rating_average', 'ratingAverage'))->addFlags(new WriteProtected(), new Inherited()),
            (new ListField('category_tree', 'categoryTree', IdField::class))->addFlags(new Inherited(), new WriteProtected()),
            (new ManyToManyIdField('property_ids', 'propertyIds', 'properties'))->addFlags(new Inherited()),
            (new ManyToManyIdField('option_ids', 'optionIds', 'options'))->addFlags(new Inherited()),
            (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new Inherited()),
            (new ListingPriceField('listing_prices', 'listingPrices'))->addFlags(new WriteProtected(), new Inherited(), new ReadProtected(SalesChannelApiSource::class)),
            new ChildCountField(),
            (new BlacklistRuleField())->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new WhitelistRuleField())->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new BoolField('custom_field_set_selection_active', 'customFieldSetSelectionActive'))->addFlags(new Inherited()),

            (new TranslatedField('metaDescription'))->addFlags(new Inherited()),
            (new TranslatedField('name'))->addFlags(new Inherited(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('keywords'))->addFlags(new Inherited()),
            (new TranslatedField('description'))->addFlags(new Inherited()),
            (new TranslatedField('metaTitle'))->addFlags(new Inherited()),
            (new TranslatedField('packUnit'))->addFlags(new Inherited()),
            (new TranslatedField('packUnitPlural'))->addFlags(new Inherited()),
            (new TranslatedField('customFields'))->addFlags(new Inherited()),

            // associations
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),

            (new ManyToOneAssociationField('deliveryTime', 'delivery_time_id', DeliveryTimeDefinition::class))
                ->addFlags(new Inherited()),

            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, 'id', true))
                ->addFlags(new Inherited()),

            (new ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, 'id'))
                ->addFlags(new Inherited()),

            (new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, 'id'))
                ->addFlags(new Inherited()),

            (new ManyToOneAssociationField('cover', 'product_media_id', ProductMediaDefinition::class, 'id'))
                ->addFlags(new Inherited()),

            (new OneToManyAssociationField('prices', ProductPriceDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete(), new Inherited(), new ReadProtected(SalesChannelApiSource::class)),

            (new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('crossSellings', ProductCrossSellingDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('crossSellingAssignedProducts', ProductCrossSellingAssignedProductsDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('configuratorSettings', ProductConfiguratorSettingDefinition::class, 'product_id', 'id'))
                ->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),

            (new OneToManyAssociationField('visibilities', ProductVisibilityDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete(), new Inherited(), new ReadProtected(SalesChannelApiSource::class)),

            (new OneToManyAssociationField('searchKeywords', ProductSearchKeywordDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete(false), new ReadProtected(SalesChannelApiSource::class)),

            (new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete(false)),

            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete()),

            new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key'),

            (new OneToManyAssociationField('orderLineItems', OrderLineItemDefinition::class, 'product_id'))
                ->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),

            (new ManyToManyAssociationField('options', PropertyGroupOptionDefinition::class, ProductOptionDefinition::class, 'product_id', 'property_group_option_id'))
                ->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField('properties', PropertyGroupOptionDefinition::class, ProductPropertyDefinition::class, 'product_id', 'property_group_option_id'))
                ->addFlags(new CascadeDelete(), new Inherited()),

            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id'))
                ->addFlags(new CascadeDelete(), new Inherited()),

            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, ProductCategoryTreeDefinition::class, 'product_id', 'category_id'))
                ->addFlags(new CascadeDelete(false), new WriteProtected()),

            (new ManyToManyAssociationField('tags', TagDefinition::class, ProductTagDefinition::class, 'product_id', 'tag_id'))
                ->addFlags(new CascadeDelete(), new Inherited()),

            (new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id'))
                ->addFlags(new Inherited(), new Required()),

            (new ManyToManyAssociationField('customFieldSets', CustomFieldSetDefinition::class, ProductCustomFieldSetDefinition::class, 'product_id', 'custom_field_set_id'))
                ->addFlags(new CascadeDelete(), new Inherited()),
        ]);

        $collection->add(
            (new ListField('variation', 'variation', StringField::class))->addFlags(new Runtime())
        );

        $collection->add(
            (new FkField('product_feature_set_id', 'featureSetId', ProductFeatureSetDefinition::class))
                ->addFlags(new Inherited())
        );
        $collection->add(
            (new ManyToOneAssociationField('featureSet', 'product_feature_set_id', ProductFeatureSetDefinition::class, 'id'))
                ->addFlags(new Inherited())
        );

        return $collection;
    }
}
