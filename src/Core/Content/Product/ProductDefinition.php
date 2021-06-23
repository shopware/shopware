<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
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
use Shopware\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
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
            'restockTime' => null,
            'active' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->addFlags(new ApiAware(), new Inherited(), new Required()),
            (new FkField('unit_id', 'unitId', UnitDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new ApiAware(), new Inherited(), new Required()),
            (new FkField('product_media_id', 'coverId', ProductMediaDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new ReferenceVersionField(ProductMediaDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new FkField('delivery_time_id', 'deliveryTimeId', DeliveryTimeDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new FkField('product_feature_set_id', 'featureSetId', ProductFeatureSetDefinition::class))->addFlags(new Inherited()),
            (new FkField('canonical_product_id', 'canonicalProductId', ProductDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Inherited(), new Required(), new ApiAware()),

            (new PriceField('price', 'price'))->addFlags(new Inherited(), new Required()),
            (new NumberRangeField('product_number', 'productNumber'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING, false), new Required()),
            (new IntField('stock', 'stock'))->addFlags(new ApiAware(), new Required()),
            (new IntField('restock_time', 'restockTime'))->addFlags(new ApiAware(), new Inherited()),
            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(), new Inherited()),
            (new IntField('available_stock', 'availableStock'))->addFlags(new ApiAware(), new WriteProtected()),
            (new BoolField('available', 'available'))->addFlags(new ApiAware(), new WriteProtected()),
            (new BoolField('is_closeout', 'isCloseout'))->addFlags(new ApiAware(), new Inherited()),
            (new ListField('variation', 'variation', StringField::class))->addFlags(new Runtime()),

            (new StringField('display_group', 'displayGroup'))->addFlags(new ApiAware(), new WriteProtected()),
            (new JsonField('configurator_group_config', 'configuratorGroupConfig'))->addFlags(new Inherited()),
            (new FkField('main_variant_id', 'mainVariantId', ProductDefinition::class))->addFlags(new ApiAware(), new Inherited()),
            (new JsonField('variant_restrictions', 'variantRestrictions')),
            (new StringField('manufacturer_number', 'manufacturerNumber'))->addFlags(new ApiAware(), new Inherited(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false)),
            (new StringField('ean', 'ean'))->addFlags(new ApiAware(), new Inherited(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false)),
            (new IntField('purchase_steps', 'purchaseSteps', 1))->addFlags(new ApiAware(), new Inherited()),
            (new IntField('max_purchase', 'maxPurchase'))->addFlags(new ApiAware(), new Inherited()),
            (new IntField('min_purchase', 'minPurchase', 1))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('purchase_unit', 'purchaseUnit'))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('reference_unit', 'referenceUnit'))->addFlags(new ApiAware(), new Inherited()),
            (new BoolField('shipping_free', 'shippingFree'))->addFlags(new ApiAware(), new Inherited()),
            (new PriceField('purchase_prices', 'purchasePrices'))->addFlags(new Inherited()),
            (new BoolField('mark_as_topseller', 'markAsTopseller'))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('weight', 'weight'))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('width', 'width'))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('height', 'height'))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('length', 'length'))->addFlags(new ApiAware(), new Inherited()),
            (new DateTimeField('release_date', 'releaseDate'))->addFlags(new ApiAware(), new Inherited()),
            (new FloatField('rating_average', 'ratingAverage'))->addFlags(new ApiAware(), new WriteProtected(), new Inherited()),
            (new ListField('category_tree', 'categoryTree', IdField::class))->addFlags(new ApiAware(), new Inherited(), new WriteProtected()),
            (new ManyToManyIdField('property_ids', 'propertyIds', 'properties'))->addFlags(new ApiAware(), new Inherited()),
            (new ManyToManyIdField('option_ids', 'optionIds', 'options'))->addFlags(new ApiAware(), new Inherited()),
            (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new Inherited()),
            (new ManyToManyIdField('category_ids', 'categoryIds', 'categories'))->addFlags(new Inherited(), new ApiAware()),
            (new ChildCountField())->addFlags(new ApiAware()),
            (new BoolField('custom_field_set_selection_active', 'customFieldSetSelectionActive'))->addFlags(new Inherited()),
            (new IntField('sales', 'sales'))->addFlags(new ApiAware(), new WriteProtected()),
            (new CheapestPriceField('cheapest_price', 'cheapestPrice'))->addFlags(new WriteProtected(), new Inherited()),

            (new TranslatedField('metaDescription'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new Inherited(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('keywords'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('description'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('packUnit'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('packUnitPlural'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('slotConfig'))->addFlags(new Inherited()),
            (new TranslatedField('customSearchKeywords'))->addFlags(new Inherited(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            // associations
            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware()),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('deliveryTime', 'delivery_time_id', DeliveryTimeDefinition::class))->addFlags(new ApiAware(), new Inherited()),

            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, 'id', true))->addFlags(new ApiAware(), new Inherited()),

            (new ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited()),

            (new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited()),

            (new ManyToOneAssociationField('cover', 'product_media_id', ProductMediaDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited()),

            (new ManyToOneAssociationField('featureSet', 'product_feature_set_id', ProductFeatureSetDefinition::class, 'id'))->addFlags(new Inherited()),

            (new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware(), new Inherited()),

            (new ManyToOneAssociationField('canonicalProduct', 'canonical_product_id', ProductDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited()),

            (new OneToManyAssociationField('prices', ProductPriceDefinition::class, 'product_id'))->addFlags(new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('crossSellings', ProductCrossSellingDefinition::class, 'product_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('crossSellingAssignedProducts', ProductCrossSellingAssignedProductsDefinition::class, 'product_id'))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('configuratorSettings', ProductConfiguratorSettingDefinition::class, 'product_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete()),

            (new OneToManyAssociationField('visibilities', ProductVisibilityDefinition::class, 'product_id'))->addFlags(new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('searchKeywords', ProductSearchKeywordDefinition::class, 'product_id'))->addFlags(new CascadeDelete(false)),

            (new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'product_id'))->addFlags(new ApiAware(), new CascadeDelete(false)),

            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'product_id'))->addFlags(new ApiAware(), new CascadeDelete()),

            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key'))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('orderLineItems', OrderLineItemDefinition::class, 'product_id'))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField('wishlists', CustomerWishlistProductDefinition::class, 'product_id'))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField('options', PropertyGroupOptionDefinition::class, ProductOptionDefinition::class, 'product_id', 'property_group_option_id'))->addFlags(new ApiAware(), new CascadeDelete()),

            (new ManyToManyAssociationField('properties', PropertyGroupOptionDefinition::class, ProductPropertyDefinition::class, 'product_id', 'property_group_option_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited()),

            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),

            (new ManyToManyAssociationField('streams', ProductStreamDefinition::class, ProductStreamMappingDefinition::class, 'product_id', 'product_stream_id'))->addFlags(new ApiAware(), new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),

            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, ProductCategoryTreeDefinition::class, 'product_id', 'category_id'))->addFlags(new ApiAware(), new CascadeDelete(false), new WriteProtected()),

            (new ManyToManyAssociationField('tags', TagDefinition::class, ProductTagDefinition::class, 'product_id', 'tag_id'))->addFlags(new CascadeDelete(), new Inherited(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),

            (new ManyToManyAssociationField('customFieldSets', CustomFieldSetDefinition::class, ProductCustomFieldSetDefinition::class, 'product_id', 'custom_field_set_id'))->addFlags(new CascadeDelete(), new Inherited()),

            (new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id'))->addFlags(new ApiAware(), new Inherited(), new Required()),
        ]);

        return $collection;
    }
}
