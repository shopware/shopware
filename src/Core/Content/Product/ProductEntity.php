<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\System\Unit\UnitEntity;

class ProductEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var int
     */
    protected $childCount;

    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var string|null
     */
    protected $taxId;

    /**
     * @var string|null
     */
    protected $manufacturerId;

    /**
     * @var string|null
     */
    protected $unitId;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string|null
     */
    protected $displayGroup;

    /**
     * @var PriceCollection|null
     */
    protected $price;

    /**
     * @var string|null
     */
    protected $manufacturerNumber;

    /**
     * @var string|null
     */
    protected $ean;

    /**
     * @var string
     */
    protected $productNumber;

    /**
     * @var int
     */
    protected $stock;

    /**
     * @var int|null
     */
    protected $availableStock;

    /**
     * @var bool
     */
    protected $available;

    /**
     * @var string|null
     */
    protected $deliveryTimeId;

    /**
     * @var DeliveryTimeEntity|null
     */
    protected $deliveryTime;

    /**
     * @var int
     */
    protected $restockTime;

    /**
     * @var bool|null
     */
    protected $isCloseout;

    /**
     * @var int|null
     */
    protected $purchaseSteps;

    /**
     * @var int|null
     */
    protected $maxPurchase;

    /**
     * @var int|null
     */
    protected $minPurchase;

    /**
     * @var float|null
     */
    protected $purchaseUnit;

    /**
     * @var float|null
     */
    protected $referenceUnit;

    /**
     * @var bool|null
     */
    protected $shippingFree;

    /**
     * @var float|null
     */
    protected $purchasePrice;

    /**
     * @var bool|null
     */
    protected $markAsTopseller;

    /**
     * @var float|null
     */
    protected $weight;

    /**
     * @var float|null
     */
    protected $width;

    /**
     * @var float|null
     */
    protected $height;

    /**
     * @var float|null
     */
    protected $length;

    /**
     * @var \DateTimeInterface|null
     */
    protected $releaseDate;

    /**
     * @var array|null
     */
    protected $categoryTree;

    /**
     * @var array|null
     */
    protected $optionIds;

    /**
     * @var array|null
     */
    protected $propertyIds;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $keywords;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $metaDescription;

    /**
     * @var string|null
     */
    protected $metaTitle;

    /**
     * @var string|null
     */
    protected $packUnit;

    /**
     * @var string|null
     */
    protected $packUnitPlural;

    /**
     * @var array|null
     */
    protected $variantRestrictions;

    /**
     * @var string[]|null
     */
    protected $configuratorGroupConfig;

    /**
     * @var bool
     */
    protected $grouped = false;

    /**
     * @var string|null
     */
    protected $mainVariantId;

    /**
     * @var array
     */
    protected $variation = [];

    /**
     * @var TaxEntity|null
     */
    protected $tax;

    /**
     * @var ProductManufacturerEntity|null
     */
    protected $manufacturer;

    /**
     * @var UnitEntity|null
     */
    protected $unit;

    /**
     * @var ProductPriceCollection
     */
    protected $prices;

    /**
     * @var ListingPriceCollection|null
     */
    protected $listingPrices;

    /**
     * @var ProductMediaEntity|null
     */
    protected $cover;

    /**
     * @var ProductEntity|null
     */
    protected $parent;

    /**
     * @var ProductCollection|null
     */
    protected $children;

    /**
     * @var ProductMediaCollection|null
     */
    protected $media;

    /**
     * @var ProductSearchKeywordCollection|null
     */
    protected $searchKeywords;

    /**
     * @var ProductTranslationCollection|null
     */
    protected $translations;

    /**
     * @var CategoryCollection|null
     */
    protected $categories;

    /**
     * @var CustomFieldSetCollection|null
     */
    protected $customFieldSets;

    /**
     * @var TagCollection|null
     */
    protected $tags;

    /**
     * @var PropertyGroupOptionCollection|null
     */
    protected $properties;

    /**
     * @var PropertyGroupOptionCollection|null
     */
    protected $options;

    /**
     * @var ProductConfiguratorSettingCollection|null
     */
    protected $configuratorSettings;

    /**
     * @var CategoryCollection|null
     */
    protected $categoriesRo;

    /**
     * @var string|null
     */
    protected $coverId;

    /**
     * @var array|null
     */
    protected $blacklistIds;

    /**
     * @var array|null
     */
    protected $whitelistIds;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var ProductVisibilityCollection|null
     */
    protected $visibilities;

    /**
     * @var array|null
     */
    protected $tagIds;

    /**
     * @var ProductReviewCollection|null
     */
    protected $productReviews;

    /**
     * @var float|null
     */
    protected $ratingAverage;

    /**
     * @var MainCategoryCollection|null
     */
    protected $mainCategories;

    /**
     * @var SeoUrlCollection|null
     */
    protected $seoUrls;

    /**
     * @var OrderLineItemCollection|null
     */
    protected $orderLineItems;

    /**
     * @var ProductCrossSellingCollection|null
     */
    protected $crossSellings;

    /**
     * @var ProductCrossSellingAssignedProductsCollection|null
     */
    protected $crossSellingAssignedProducts;

    /**
     * @var string|null
     */
    protected $featureSetId;

    /**
     * @var ProductFeatureSetEntity|null
     */
    protected $featureSet;

    /**
     * @var bool|null
     */
    protected $customFieldSetSelectionActive;

    public function __construct()
    {
        $this->prices = new ProductPriceCollection();
    }

    public function __toString()
    {
        return (string) $this->getName();
    }

    public function getProductReviews(): ?ProductReviewCollection
    {
        return $this->productReviews;
    }

    public function setProductReviews(ProductReviewCollection $productReviews): void
    {
        $this->productReviews = $productReviews;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getManufacturerId(): ?string
    {
        return $this->manufacturerId;
    }

    public function setManufacturerId(?string $manufacturerId): void
    {
        $this->manufacturerId = $manufacturerId;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function setUnitId(?string $unitId): void
    {
        $this->unitId = $unitId;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPrice(): ?PriceCollection
    {
        return $this->price;
    }

    public function setPrice(PriceCollection $price): void
    {
        $this->price = $price;
    }

    public function getCurrencyPrice(string $currencyId): ?Price
    {
        if ($this->price === null) {
            return null;
        }

        return $this->price->getCurrencyPrice($currencyId);
    }

    public function getManufacturerNumber(): ?string
    {
        return $this->manufacturerNumber;
    }

    public function setManufacturerNumber(?string $manufacturerNumber): void
    {
        $this->manufacturerNumber = $manufacturerNumber;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getIsCloseout(): bool
    {
        return (bool) $this->isCloseout;
    }

    public function setIsCloseout(?bool $isCloseout): void
    {
        $this->isCloseout = $isCloseout;
    }

    public function getPurchaseSteps(): ?int
    {
        return $this->purchaseSteps;
    }

    public function setPurchaseSteps(?int $purchaseSteps): void
    {
        $this->purchaseSteps = $purchaseSteps;
    }

    public function getMaxPurchase(): ?int
    {
        return $this->maxPurchase;
    }

    public function setMaxPurchase(?int $maxPurchase): void
    {
        $this->maxPurchase = $maxPurchase;
    }

    public function getMinPurchase(): ?int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(?int $minPurchase): void
    {
        $this->minPurchase = $minPurchase;
    }

    public function getPurchaseUnit(): ?float
    {
        return $this->purchaseUnit;
    }

    public function setPurchaseUnit(?float $purchaseUnit): void
    {
        $this->purchaseUnit = $purchaseUnit;
    }

    public function getReferenceUnit(): ?float
    {
        return $this->referenceUnit;
    }

    public function setReferenceUnit(?float $referenceUnit): void
    {
        $this->referenceUnit = $referenceUnit;
    }

    public function getShippingFree(): ?bool
    {
        return $this->shippingFree;
    }

    public function setShippingFree(?bool $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getPurchasePrice(): ?float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(?float $purchasePrice): void
    {
        $this->purchasePrice = $purchasePrice;
    }

    public function getMarkAsTopseller(): ?bool
    {
        return $this->markAsTopseller;
    }

    public function setMarkAsTopseller(?bool $markAsTopseller): void
    {
        $this->markAsTopseller = $markAsTopseller;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(?float $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function setLength(?float $length): void
    {
        $this->length = $length;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeInterface $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }

    public function getCategoryTree(): ?array
    {
        return $this->categoryTree;
    }

    public function setCategoryTree(?array $categoryTree): void
    {
        $this->categoryTree = $categoryTree;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getPackUnit(): ?string
    {
        return $this->packUnit;
    }

    public function setPackUnit(?string $packUnit): void
    {
        $this->packUnit = $packUnit;
    }

    public function getPackUnitPlural(): ?string
    {
        return $this->packUnitPlural;
    }

    public function setPackUnitPlural(?string $packUnitPlural): void
    {
        $this->packUnitPlural = $packUnitPlural;
    }

    public function getTax(): ?TaxEntity
    {
        return $this->tax;
    }

    public function setTax(TaxEntity $tax): void
    {
        $this->tax = $tax;
    }

    public function getManufacturer(): ?ProductManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function setManufacturer(ProductManufacturerEntity $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getUnit(): ?UnitEntity
    {
        return $this->unit;
    }

    public function setUnit(UnitEntity $unit): void
    {
        $this->unit = $unit;
    }

    public function getPrices(): ?ProductPriceCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductPriceCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getListingPrices(): ?ListingPriceCollection
    {
        return $this->listingPrices;
    }

    public function setListingPrices(ListingPriceCollection $listingPrices): void
    {
        $this->listingPrices = $listingPrices;
    }

    public function getRestockTime(): int
    {
        return $this->restockTime;
    }

    public function setRestockTime(int $restockTime): void
    {
        $this->restockTime = $restockTime;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return new DeliveryDate(
            (new \DateTime())
                ->add(new \DateInterval('P' . 1 . 'D')),
            (new \DateTime())
                ->add(new \DateInterval('P' . 1 . 'D'))
                ->add(new \DateInterval('P' . 1 . 'D'))
        );
    }

    public function getRestockDeliveryDate(): DeliveryDate
    {
        $deliveryDate = $this->getDeliveryDate();

        return $deliveryDate->add(new \DateInterval('P' . $this->getRestockTime() . 'D'));
    }

    public function isReleased(): bool
    {
        if (!$this->getReleaseDate()) {
            return true;
        }

        return $this->releaseDate < new \DateTime();
    }

    public function getOptionIds(): ?array
    {
        return $this->optionIds;
    }

    public function setOptionIds(?array $optionIds): void
    {
        $this->optionIds = $optionIds;
    }

    public function getPropertyIds(): ?array
    {
        return $this->propertyIds;
    }

    public function setPropertyIds(?array $propertyIds): void
    {
        $this->propertyIds = $propertyIds;
    }

    public function getCover(): ?ProductMediaEntity
    {
        return $this->cover;
    }

    public function setCover(ProductMediaEntity $cover): void
    {
        $this->cover = $cover;
    }

    public function getParent(): ?ProductEntity
    {
        return $this->parent;
    }

    public function setParent(ProductEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?ProductCollection
    {
        return $this->children;
    }

    public function setChildren(ProductCollection $children): void
    {
        $this->children = $children;
    }

    public function getMedia(): ?ProductMediaCollection
    {
        return $this->media;
    }

    public function setMedia(ProductMediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getSearchKeywords(): ?ProductSearchKeywordCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(ProductSearchKeywordCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }

    public function getTranslations(): ?ProductTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getCustomFieldSets(): ?CustomFieldSetCollection
    {
        return $this->customFieldSets;
    }

    public function setCustomFieldSets(CustomFieldSetCollection $customFieldSets): void
    {
        $this->customFieldSets = $customFieldSets;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getProperties(): ?PropertyGroupOptionCollection
    {
        return $this->properties;
    }

    public function setProperties(PropertyGroupOptionCollection $properties): void
    {
        $this->properties = $properties;
    }

    public function getOptions(): ?PropertyGroupOptionCollection
    {
        return $this->options;
    }

    public function setOptions(PropertyGroupOptionCollection $options): void
    {
        $this->options = $options;
    }

    public function getConfiguratorSettings(): ?ProductConfiguratorSettingCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(ProductConfiguratorSettingCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    public function setGrouped(bool $grouped): void
    {
        $this->grouped = $grouped;
    }

    public function isGrouped(): bool
    {
        return $this->grouped;
    }

    public function getCategoriesRo(): ?CategoryCollection
    {
        return $this->categoriesRo;
    }

    public function setCategoriesRo(CategoryCollection $categoriesRo): void
    {
        $this->categoriesRo = $categoriesRo;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getCoverId(): ?string
    {
        return $this->coverId;
    }

    public function setCoverId(string $coverId): void
    {
        $this->coverId = $coverId;
    }

    public function getBlacklistIds(): ?array
    {
        return $this->blacklistIds;
    }

    public function setBlacklistIds(?array $blacklistIds): void
    {
        $this->blacklistIds = $blacklistIds;
    }

    public function getWhitelistIds(): ?array
    {
        return $this->whitelistIds;
    }

    public function setWhitelistIds(?array $whitelistIds): void
    {
        $this->whitelistIds = $whitelistIds;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getVisibilities(): ?ProductVisibilityCollection
    {
        return $this->visibilities;
    }

    public function setVisibilities(ProductVisibilityCollection $visibilities): void
    {
        $this->visibilities = $visibilities;
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function setProductNumber(string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getTagIds(): ?array
    {
        return $this->tagIds;
    }

    public function setTagIds(array $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    public function getVariantRestrictions(): ?array
    {
        return $this->variantRestrictions;
    }

    public function setVariantRestrictions(?array $variantRestrictions): void
    {
        $this->variantRestrictions = $variantRestrictions;
    }

    public function getConfiguratorGroupConfig(): ?array
    {
        return $this->configuratorGroupConfig;
    }

    public function setConfiguratorGroupConfig(?array $configuratorGroupConfig): void
    {
        $this->configuratorGroupConfig = $configuratorGroupConfig;
    }

    public function getMainVariantId(): ?string
    {
        return $this->mainVariantId;
    }

    public function setMainVariantId(?string $mainVariantId): void
    {
        $this->mainVariantId = $mainVariantId;
    }

    public function getVariation(): array
    {
        return $this->variation;
    }

    public function setVariation(array $variation): void
    {
        $this->variation = $variation;
    }

    public function getAvailableStock(): ?int
    {
        return $this->availableStock;
    }

    public function setAvailableStock(int $availableStock): void
    {
        $this->availableStock = $availableStock;
    }

    public function getAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function getDeliveryTimeId(): ?string
    {
        return $this->deliveryTimeId;
    }

    public function setDeliveryTimeId(?string $deliveryTimeId): void
    {
        $this->deliveryTimeId = $deliveryTimeId;
    }

    public function getDeliveryTime(): ?DeliveryTimeEntity
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(?DeliveryTimeEntity $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }

    public function getChildCount(): ?int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getRatingAverage(): ?float
    {
        return $this->ratingAverage;
    }

    public function setRatingAverage(?float $ratingAverage): void
    {
        $this->ratingAverage = $ratingAverage;
    }

    public function getDisplayGroup(): ?string
    {
        return $this->displayGroup;
    }

    public function setDisplayGroup(?string $displayGroup): void
    {
        $this->displayGroup = $displayGroup;
    }

    public function getMainCategories(): ?MainCategoryCollection
    {
        return $this->mainCategories;
    }

    public function setMainCategories(MainCategoryCollection $mainCategories): void
    {
        $this->mainCategories = $mainCategories;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getOrderLineItems(): ?OrderLineItemCollection
    {
        return $this->orderLineItems;
    }

    public function setOrderLineItems(OrderLineItemCollection $orderLineItems): void
    {
        $this->orderLineItems = $orderLineItems;
    }

    public function getCrossSellings(): ?ProductCrossSellingCollection
    {
        return $this->crossSellings;
    }

    public function setCrossSellings(ProductCrossSellingCollection $crossSellings): void
    {
        $this->crossSellings = $crossSellings;
    }

    public function getCrossSellingAssignedProducts(): ?ProductCrossSellingAssignedProductsCollection
    {
        return $this->crossSellingAssignedProducts;
    }

    public function setCrossSellingAssignedProducts(ProductCrossSellingAssignedProductsCollection $crossSellingAssignedProducts): void
    {
        $this->crossSellingAssignedProducts = $crossSellingAssignedProducts;
    }

    public function getFeatureSetId(): ?string
    {
        return $this->featureSetId;
    }

    public function setFeatureSetId(?string $featureSetId): void
    {
        $this->featureSetId = $featureSetId;
    }

    public function getFeatureSet(): ?ProductFeatureSetEntity
    {
        return $this->featureSet;
    }

    public function setFeatureSet(ProductFeatureSetEntity $featureSet): void
    {
        $this->featureSet = $featureSet;
    }

    public function getApiAlias(): string
    {
        return 'product';
    }

    public function getCustomFieldSetSelectionActive(): ?bool
    {
        return $this->customFieldSetSelectionActive;
    }

    public function setCustomFieldSetSelectionActive(?bool $customFieldSetSelectionActive): void
    {
        $this->customFieldSetSelectionActive = $customFieldSetSelectionActive;
    }
}
