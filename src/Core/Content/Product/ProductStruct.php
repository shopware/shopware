<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerStruct;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaStruct;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleStruct;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\System\Tax\TaxStruct;
use Shopware\Core\System\Unit\UnitStruct;

class ProductStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var int
     */
    protected $catalogId;

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
     * @var PriceStruct
     */
    protected $price;

    /**
     * @var string|null
     */
    protected $supplierNumber;

    /**
     * @var string|null
     */
    protected $ean;

    /**
     * @var int|null
     */
    protected $stock;

    /**
     * @var int
     */
    protected $minDeliveryTime;

    /**
     * @var int
     */
    protected $maxDeliveryTime;

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
    protected $minStock;

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
     * @var int|null
     */
    protected $pseudoSales;

    /**
     * @var bool|null
     */
    protected $markAsTopseller;

    /**
     * @var int|null
     */
    protected $sales;

    /**
     * @var int|null
     */
    protected $position;

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
     * @var string|null
     */
    protected $template;

    /**
     * @var bool|null
     */
    protected $allowNotification;

    /**
     * @var \DateTime|null
     */
    protected $releaseDate;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var array|null
     */
    protected $categoryTree;

    /**
     * @var array|null
     */
    protected $variationIds;

    /**
     * @var array|null
     */
    protected $datasheetIds;

    /**
     * @var string|null
     */
    protected $additionalText;

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
    protected $descriptionLong;

    /**
     * @var string|null
     */
    protected $metaTitle;

    /**
     * @var string|null
     */
    protected $packUnit;

    /**
     * @var TaxStruct|null
     */
    protected $tax;

    /**
     * @var ProductManufacturerStruct|null
     */
    protected $manufacturer;

    /**
     * @var UnitStruct|null
     */
    protected $unit;

    /**
     * @var ProductPriceRuleCollection
     */
    protected $priceRules;

    /**
     * @var PriceRuleCollection|null
     */
    protected $listingPrices;

    /**
     * @var ProductMediaStruct|null
     */
    protected $cover;

    /**
     * @var ProductStruct|null
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
     * @var SearchDocumentCollection|null
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
     * @var ConfigurationGroupOptionCollection|null
     */
    protected $datasheet;

    /**
     * @var ConfigurationGroupOptionCollection|null
     */
    protected $variations;

    /**
     * @var ProductConfiguratorCollection|null
     */
    protected $configurators;

    /**
     * @var ProductServiceCollection|null
     */
    protected $services;

    /**
     * @var CategoryCollection|null
     */
    protected $categoriesRo;

    /**
     * @var CatalogStruct|null
     */
    protected $catalog;

    public function __construct()
    {
        $this->priceRules = new ProductPriceRuleCollection();
    }

    public function __toString()
    {
        return (string) $this->getName();
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

    public function getPrice(): PriceStruct
    {
        return $this->price;
    }

    public function setPrice(PriceStruct $price): void
    {
        $this->price = $price;
    }

    public function getSupplierNumber(): ?string
    {
        return $this->supplierNumber;
    }

    public function setSupplierNumber(?string $supplierNumber): void
    {
        $this->supplierNumber = $supplierNumber;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): void
    {
        $this->stock = $stock;
    }

    public function getIsCloseout(): ?bool
    {
        return $this->isCloseout;
    }

    public function setIsCloseout(?bool $isCloseout): void
    {
        $this->isCloseout = $isCloseout;
    }

    public function getMinStock(): ?int
    {
        return $this->minStock;
    }

    public function setMinStock(?int $minStock): void
    {
        $this->minStock = $minStock;
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

    public function getPseudoSales(): ?int
    {
        return $this->pseudoSales;
    }

    public function setPseudoSales(?int $pseudoSales): void
    {
        $this->pseudoSales = $pseudoSales;
    }

    public function getMarkAsTopseller(): ?bool
    {
        return $this->markAsTopseller;
    }

    public function setMarkAsTopseller(?bool $markAsTopseller): void
    {
        $this->markAsTopseller = $markAsTopseller;
    }

    public function getSales(): ?int
    {
        return $this->sales;
    }

    public function setSales(?int $sales): void
    {
        $this->sales = $sales;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function getAllowNotification(): ?bool
    {
        return $this->allowNotification;
    }

    public function setAllowNotification(?bool $allowNotification): void
    {
        $this->allowNotification = $allowNotification;
    }

    public function getReleaseDate(): ?\DateTime
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTime $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCategoryTree(): ?array
    {
        return $this->categoryTree;
    }

    public function setCategoryTree(?array $categoryTree): void
    {
        $this->categoryTree = $categoryTree;
    }

    public function getAdditionalText(): ?string
    {
        return $this->additionalText;
    }

    public function setAdditionalText(?string $additionalText): void
    {
        $this->additionalText = $additionalText;
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

    public function getDescriptionLong(): ?string
    {
        return $this->descriptionLong;
    }

    public function setDescriptionLong(?string $descriptionLong): void
    {
        $this->descriptionLong = $descriptionLong;
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

    public function getTax(): ?TaxStruct
    {
        return $this->tax;
    }

    public function setTax(TaxStruct $tax): void
    {
        $this->tax = $tax;
    }

    public function getManufacturer(): ?ProductManufacturerStruct
    {
        return $this->manufacturer;
    }

    public function setManufacturer(ProductManufacturerStruct $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getUnit(): ?UnitStruct
    {
        return $this->unit;
    }

    public function setUnit(UnitStruct $unit): void
    {
        $this->unit = $unit;
    }

    public function getPriceRules(): ProductPriceRuleCollection
    {
        return $this->priceRules;
    }

    public function setPriceRules(ProductPriceRuleCollection $priceRules): void
    {
        $this->priceRules = $priceRules;
    }

    public function getListingPrices(): ?PriceRuleCollection
    {
        return $this->listingPrices;
    }

    public function setListingPrices(PriceRuleCollection $listingPrices): void
    {
        $this->listingPrices = $listingPrices;
    }

    public function getMinDeliveryTime(): int
    {
        return $this->minDeliveryTime;
    }

    public function setMinDeliveryTime(int $minDeliveryTime): void
    {
        $this->minDeliveryTime = $minDeliveryTime;
    }

    public function getRestockTime(): int
    {
        return $this->restockTime;
    }

    public function setRestockTime(int $restockTime): void
    {
        $this->restockTime = $restockTime;
    }

    public function getMaxDeliveryTime(): int
    {
        return $this->maxDeliveryTime;
    }

    public function setMaxDeliveryTime(int $maxDeliveryTime): void
    {
        $this->maxDeliveryTime = $maxDeliveryTime;
    }

    public function getPriceRuleDefinitions(Context $context): PriceDefinitionCollection
    {
        $taxRules = $this->getTaxRuleCollection();

        $rules = $this->getPriceRules();

        $prices = $rules->getPriceRulesForContext($context);

        if (!$prices) {
            return new PriceDefinitionCollection();
        }

        /* @var ProductPriceRuleCollection $prices */
        $prices->sortByQuantity();

        $definitions = $prices->map(function (ProductPriceRuleStruct $rule) use ($taxRules) {
            $quantity = $rule->getQuantityEnd() ?? $rule->getQuantityStart();

            return new QuantityPriceDefinition($rule->getPrice()->getGross(), $taxRules, $quantity, true);
        });

        return new PriceDefinitionCollection($definitions);
    }

    public function getPriceDefinition(Context $context): QuantityPriceDefinition
    {
        return new QuantityPriceDefinition($this->getPrice()->getGross(), $this->getTaxRuleCollection(), 1, true);
    }

    public function getListingPriceDefinition(Context $context): QuantityPriceDefinition
    {
        $taxRules = $this->getTaxRuleCollection();

        if ($this->getListingPrices()) {
            $prices = $this->getListingPrices();
        } else {
            $prices = $this->getPriceRules()->filter(
                function (ProductPriceRuleStruct $price) {
                    return $price->getQuantityEnd() === null;
                }
            );
        }

        $prices = $prices->getPriceRulesForContext($context);

        if (!$prices) {
            return new QuantityPriceDefinition($this->getPrice()->getGross(), $taxRules, 1, true);
        }

        if ($prices->count() <= 0) {
            return new QuantityPriceDefinition($this->getPrice()->getGross(), $taxRules, 1, true);
        }

        /** @var ProductPriceRuleStruct $price */
        $price = $prices->first();

        return new QuantityPriceDefinition($price->getPrice()->getGross(), $taxRules, 1, true);
    }

    public function getPriceDefinitionForQuantity(Context $context, int $quantity): QuantityPriceDefinition
    {
        // TODO@DR consider tax state of salesChannel context (NEXT-286)
        $taxRules = $this->getTaxRuleCollection();

        /** @var ProductPriceRuleCollection $rules */
        $rules = $this->getPriceRules();

        $prices = $rules->getPriceRulesForContext($context);

        if (!$prices) {
            return new QuantityPriceDefinition($this->getPrice()->getGross(), $taxRules, $quantity, true);
        }

        /** @var ProductPriceRuleCollection $prices */
        $price = $prices->getQuantityPrice($quantity);

        return new QuantityPriceDefinition($price->getPrice()->getGross(), $taxRules, $quantity, true);
    }

    public function getTaxRuleCollection()
    {
        return new TaxRuleCollection([
            new PercentageTaxRule($this->getTax()->getTaxRate(), 100),
        ]);
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return new DeliveryDate(
            (new \DateTime())
                ->add(new \DateInterval('P' . $this->getMinDeliveryTime() . 'D')),
            (new \DateTime())
                ->add(new \DateInterval('P' . $this->getMinDeliveryTime() . 'D'))
                ->add(new \DateInterval('P' . $this->getMaxDeliveryTime() . 'D'))
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

    public function getVariationIds(): ?array
    {
        return $this->variationIds;
    }

    public function setVariationIds(?array $variationIds): void
    {
        $this->variationIds = $variationIds;
    }

    public function getDatasheetIds(): ?array
    {
        return $this->datasheetIds;
    }

    public function setDatasheetIds(?array $datasheetIds): void
    {
        $this->datasheetIds = $datasheetIds;
    }

    public function getCover(): ?ProductMediaStruct
    {
        return $this->cover;
    }

    public function setCover(ProductMediaStruct $cover): void
    {
        $this->cover = $cover;
    }

    public function getParent(): ?ProductStruct
    {
        return $this->parent;
    }

    public function setParent(ProductStruct $parent): void
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

    public function getSearchKeywords(): ?SearchDocumentCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(SearchDocumentCollection $searchKeywords): void
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

    public function getDatasheet(): ?ConfigurationGroupOptionCollection
    {
        return $this->datasheet;
    }

    public function setDatasheet(ConfigurationGroupOptionCollection $datasheet): void
    {
        $this->datasheet = $datasheet;
    }

    public function getVariations(): ?ConfigurationGroupOptionCollection
    {
        return $this->variations;
    }

    public function setVariations(ConfigurationGroupOptionCollection $variations): void
    {
        $this->variations = $variations;
    }

    public function getConfigurators(): ?ProductConfiguratorCollection
    {
        return $this->configurators;
    }

    public function setConfigurators(ProductConfiguratorCollection $configurators): void
    {
        $this->configurators = $configurators;
    }

    public function getServices(): ?ProductServiceCollection
    {
        return $this->services;
    }

    public function setServices(ProductServiceCollection $services): void
    {
        $this->services = $services;
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

    public function getCatalogId(): int
    {
        return $this->catalogId;
    }

    public function setCatalogId(int $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function getCatalog(): ?CatalogStruct
    {
        return $this->catalog;
    }

    public function setCatalog(CatalogStruct $catalog): void
    {
        $this->catalog = $catalog;
    }
}
