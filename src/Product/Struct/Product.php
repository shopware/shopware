<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Product\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\ProductManufacturer\Struct\ProductManufacturer;
use Shopware\Tax\Struct\Tax;
use Shopware\Unit\Struct\Unit;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Product extends Struct
{
    /**
     * Unique identifier of the product.
     *
     * @var string
     */
    protected $uuid;

    /**
     * Unique identifier of the product variation (s_articles_details).
     *
     * @var int
     */
    protected $variantUuid;

    /**
     * Unique identifier field.
     * Shopware order number for the product, which
     * is used to load the product or add the product
     * to the basket.
     *
     * @var string
     */
    protected $number;

    /**
     * Contains the product name.
     *
     * @var string
     */
    protected $name;

    /**
     * Stock value of the product.
     * Displays how many unit are left in the stock.
     *
     * @var int
     */
    protected $stock;

    /**
     * Short description of the product.
     * Describes the product in one or two sentences.
     *
     * @var string
     */
    protected $shortDescription;

    /**
     * A long description of the product.
     *
     * @var string
     */
    protected $longDescription;

    /**
     * Defines the date when the product was released / will be
     * released and can be ordered.
     *
     * @var \DateTime|null
     */
    protected $releaseDate;

    /**
     * Defines the required time in days to deliver the product.
     *
     * @var int
     */
    protected $shippingTime;

    /**
     * Defines if the product has no shipping costs.
     *
     * @var bool
     */
    protected $shippingFree;

    /**
     * Defines that the product are no longer
     * available if the last item is sold.
     *
     * @var bool
     */
    protected $closeouts;

    /**
     * Contains a flag if the product has properties.
     *
     * @var bool
     */
    protected $hasProperties = false;

    /**
     * Defines the date which the product was created in the
     * database.
     *
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * Defines a list of keywords for this product.
     *
     * @var array
     */
    protected $keywords;

    /**
     * Defines the meta title of the product.
     * This title is used for the title tag within the header.
     *
     * @var string
     */
    protected $metaTitle;

    /**
     * Defines if the customer can be set an email
     * notification for this product if it is sold out.
     *
     * @var bool
     */
    protected $allowsNotification;

    /**
     * Additional information text for the product variation.
     *
     * @var string
     */
    protected $additional;

    /**
     * Minimal stock value for the product.
     *
     * @var int
     */
    protected $minStock;

    /**
     * Physical height of the product.
     * Used for area calculation.
     *
     * @var float
     */
    protected $height;

    /**
     * Physical width of the product.
     * Used for area calculation.
     *
     * @var float
     */
    protected $width;

    /**
     * Physical length of the product.
     * Used for area calculation.
     *
     * @var float
     */
    protected $length;

    /**
     * Physical width of the product.
     * Used for area calculation.
     *
     * @var float
     */
    protected $weight;

    /**
     * Ean code of the product.
     *
     * @var string
     */
    protected $ean;

    /**
     * Flag if the product should be displayed
     * with a teaser flag within listings.
     *
     * @var bool
     */
    protected $highlight;

    /**
     * @var int
     */
    protected $sales;

    /**
     * @var bool
     */
    protected $hasConfigurator;

    /**
     * @var bool
     */
    protected $hasEsd;

    /**
     * @var array
     */
    protected $blockedCustomerGroupIds = [];

    /**
     * @var string
     */
    protected $manufacturerNumber;

    /**
     * @var string
     */
    protected $template;

    /**
     * Flag if the product has an available variant.
     *
     * @var bool
     */
    protected $hasAvailableVariant;

    /**
     * @var string
     */
    protected $mainVariantUuid;

    /**
     * @var bool
     */
    protected $isNew = false;

    /**
     * @var bool
     */
    protected $isTopSeller = false;

    /**
     * @var bool
     */
    protected $comingSoon = false;

    /**
     * @var Unit
     */
    protected $unit;

    /**
     * @var Tax
     */
    protected $tax;

    /**
     * @var ProductManufacturer
     */
    protected $manufacturer;

    /**
     * @var bool
     */
    protected $isMainVariant;

    //    /**
    //     * Contains the product cover which displayed
    //     * as product image in listings or sliders.
    //     *
    //     * @var Media
    //     */
    //    protected $cover;
    //
    //    /**
    //     * @var PriceGroup
    //     */
    //    protected $priceGroup;
    //
    //    /**
    //     * Contains an offset of product states.
    //     * States defines which processed the product has already passed through,
    //     * like the price calculation, translation or other states.
    //     *
    //     * @var array
    //     */
    //    protected $states = [];
    //
    //    /**
    //     * @var ProductEsd
    //     */
    //    protected $esd;

    /**
     * @return bool
     */
    public function hasProperties(): bool
    {
        return $this->hasProperties;
    }

    /**
     * @return bool
     */
    public function isShippingFree(): bool
    {
        return $this->shippingFree;
    }

    /**
     * @return bool
     */
    public function allowsNotification(): bool
    {
        return $this->allowsNotification;
    }

    /**
     * @return bool
     */
    public function highlight(): bool
    {
        return $this->highlight;
    }

    /**
     * @param bool $highlight
     */
    public function setHighlight($highlight): void
    {
        $this->highlight = $highlight;
    }

    /**
     * @param bool $allowsNotification
     */
    public function setAllowsNotification($allowsNotification): void
    {
        $this->allowsNotification = $allowsNotification;
    }

    /**
     * @param bool $shippingFree
     */
    public function setShippingFree($shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    //    /**
    //     * @param Unit $unit
    //     */
    //    public function setUnit(Unit $unit): void
    //    {
    //        $this->unit = $unit;
    //    }
    //
    //    /**
    //     * @return Unit
    //     */
    //    public function getUnit(): \Shopware\Unit\Struct\Unit
    //    {
    //        return $this->unit;
    //    }
    //
    //    /**
    //     * @param Tax $tax
    //     */
    //    public function setTax($tax): void
    //    {
    //        $this->tax = $tax;
    //    }
    //
    //    /**
    //     * @return Tax
    //     */
    //    public function getTax(): \Shopware\Tax\Struct\Tax
    //    {
    //        return $this->tax;
    //    }
    //
    //    /**
    //     * @param \Shopware\ProductManufacturer\Struct\ProductManufacturer $manufacturer
    //     */
    //    public function setManufacturer($manufacturer): void
    //    {
    //        $this->manufacturer = $manufacturer;
    //    }
    //
    //    /**
    //     * @return \Shopware\ProductManufacturer\Struct\ProductManufacturer
    //     */
    //    public function getManufacturer(): \Shopware\ProductManufacturer\Struct\ProductManufacturer
    //    {
    //        return $this->manufacturer;
    //    }
    //
    //    public function setCover(?Media $cover): void
    //    {
    //        $this->cover = $cover;
    //    }
    //
    //    public function getCover(): ? Media
    //    {
    //        return $this->cover;
    //    }

    /**
     * @param string $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $additional
     */
    public function setAdditional($additional): void
    {
        $this->additional = $additional;
    }

    /**
     * @return string
     */
    public function getAdditional(): string
    {
        return $this->additional;
    }

    /**
     * @param bool $closeouts
     */
    public function setCloseouts($closeouts): void
    {
        $this->closeouts = $closeouts;
    }

    /**
     * @return bool
     */
    public function isCloseouts(): bool
    {
        return $this->closeouts;
    }

    /**
     * @param string $ean
     */
    public function setEan($ean): void
    {
        $this->ean = $ean;
    }

    /**
     * @return string
     */
    public function getEan(): string
    {
        return $this->ean;
    }

    /**
     * @param float $height
     */
    public function setHeight($height): void
    {
        $this->height = $height;
    }

    /**
     * @return float
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * @param array $keywords
     */
    public function setKeywords($keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * @return array
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param float $length
     */
    public function setLength($length): void
    {
        $this->length = $length;
    }

    /**
     * @return float
     */
    public function getLength(): float
    {
        return $this->length;
    }

    /**
     * @param string $longDescription
     */
    public function setLongDescription($longDescription): void
    {
        $this->longDescription = $longDescription;
    }

    /**
     * @return string
     */
    public function getLongDescription(): string
    {
        return $this->longDescription;
    }

    /**
     * @param int $minStock
     */
    public function setMinStock($minStock): void
    {
        $this->minStock = $minStock;
    }

    /**
     * @return int
     */
    public function getMinStock(): int
    {
        return $this->minStock;
    }

    /**
     * @param \DateTime $releaseDate
     */
    public function setReleaseDate(?\DateTime $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }

    /**
     * @return \DateTime|null
     */
    public function getReleaseDate(): ?\DateTime
    {
        return $this->releaseDate;
    }

    /**
     * @param int $shippingTime
     */
    public function setShippingTime($shippingTime): void
    {
        $this->shippingTime = $shippingTime;
    }

    /**
     * @return int
     */
    public function getShippingTime(): int
    {
        return $this->shippingTime;
    }

    /**
     * @param string $shortDescription
     */
    public function setShortDescription($shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    /**
     * @return string
     */
    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    /**
     * @param int $stock
     */
    public function setStock($stock): void
    {
        $this->stock = $stock;
    }

    /**
     * @return int
     */
    public function getStock(): int
    {
        return $this->stock;
    }

    //    /**
    //     * @return bool
    //     */
    //    public function isAvailable(): bool
    //    {
    //        if (!$this->isCloseouts()) {
    //            return true;
    //        }
    //
    //        return $this->getStock() >= $this->getUnit()->getMinPurchase();
    //    }

    /**
     * @param float $weight
     */
    public function setWeight($weight): void
    {
        $this->weight = $weight;
    }

    /**
     * @return float
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * @param float $width
     */
    public function setWidth($width): void
    {
        $this->width = $width;
    }

    /**
     * @return float
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * @param bool $hasProperties
     */
    public function setHasProperties($hasProperties): void
    {
        $this->hasProperties = $hasProperties;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    //    /**
    //     * @return \Shopware\PriceGroup\Struct\PriceGroup
    //     */
    //    public function getPriceGroup(): \Shopware\PriceGroup\Struct\PriceGroup
    //    {
    //        return $this->priceGroup;
    //    }
    //
    //    /**
    //     * @param \Shopware\PriceGroup\Struct\PriceGroup $priceGroup
    //     */
    //    public function setPriceGroup(PriceGroup $priceGroup = null): void
    //    {
    //        $this->priceGroup = $priceGroup;
    //    }

    /**
     * @return string
     */
    public function getManufacturerNumber(): string
    {
        return $this->manufacturerNumber;
    }

    /**
     * @param string $manufacturerNumber
     */
    public function setManufacturerNumber($manufacturerNumber): void
    {
        $this->manufacturerNumber = $manufacturerNumber;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param string $template
     */
    public function setTemplate($template): void
    {
        $this->template = $template;
    }

    /**
     * @return string
     */
    public function getMetaTitle(): string
    {
        return $this->metaTitle;
    }

    /**
     * @param string $metaTitle
     */
    public function setMetaTitle($metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    /**
     * @return bool
     */
    public function hasConfigurator(): bool
    {
        return $this->hasConfigurator;
    }

    /**
     * @param bool $hasConfigurator
     */
    public function setHasConfigurator($hasConfigurator): void
    {
        $this->hasConfigurator = $hasConfigurator;
    }

    /**
     * @return int
     */
    public function getSales(): int
    {
        return $this->sales;
    }

    /**
     * @param int $sales
     */
    public function setSales($sales): void
    {
        $this->sales = $sales;
    }

    /**
     * @return bool
     */
    public function hasEsd(): bool
    {
        return $this->hasEsd;
    }

    /**
     * @param bool $hasEsd
     */
    public function setHasEsd($hasEsd): void
    {
        $this->hasEsd = $hasEsd;
    }

    //    /**
    //     * @return \Shopware\ProductEsd\Struct\ProductEsd
    //     */
    //    public function getEsd(): \Shopware\ProductEsd\Struct\ProductEsd
    //    {
    //        return $this->esd;
    //    }
    //
    //    /**
    //     * @param ProductEsd $esd
    //     */
    //    public function setEsd(ProductEsd $esd = null): void
    //    {
    //        $this->esd = $esd;
    //    }
    //
    //    /**
    //     * @return bool
    //     */
    //    public function isPriceGroupActive(): bool
    //    {
    //        return $this->isPriceGroupActive && $this->priceGroup;
    //    }
    //
    //    /**
    //     * @param bool $isPriceGroupActive
    //     */
    //    public function setIsPriceGroupActive($isPriceGroupActive): void
    //    {
    //        $this->isPriceGroupActive = $isPriceGroupActive;
    //    }

    public function getBlockedCustomerGroupUuids(): array
    {
        return $this->blockedCustomerGroupIds;
    }

    public function setBlockedCustomerGroupIds($blockedCustomerGroupUuids): void
    {
        $this->blockedCustomerGroupIds = $blockedCustomerGroupUuids;
    }

    public function hasAvailableVariant(): bool
    {
        return $this->hasAvailableVariant;
    }

    public function setHasAvailableVariant(bool $hasAvailableVariant): void
    {
        $this->hasAvailableVariant = $hasAvailableVariant;
    }

    public function getMainVariantUuid(): string
    {
        return $this->mainVariantUuid;
    }

    public function setMainVariantUuid(string $mainVariantUuid): void
    {
        $this->mainVariantUuid = $mainVariantUuid;
        $this->isMainVariant = ($this->variantUuid === $this->mainVariantUuid);
    }

    /**
     * @return bool
     */
    public function isMainVariant(): bool
    {
        return $this->isMainVariant;
    }

    public function setIsNew(bool $isNew): void
    {
        $this->isNew = $isNew;
    }

    public function setIsTopSeller(bool $isTopSeller): void
    {
        $this->isTopSeller = $isTopSeller;
    }

    public function setComingSoon(bool $comingSoon): void
    {
        $this->comingSoon = $comingSoon;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function isTopSeller(): bool
    {
        return $this->isTopSeller;
    }

    public function comingSoon(): bool
    {
        return $this->comingSoon;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getVariantUuid(): string
    {
        return $this->variantUuid;
    }

    public function setVariantUuid(string $variantUuid): void
    {
        $this->variantUuid = $variantUuid;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }
}
