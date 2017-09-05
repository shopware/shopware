<?php declare(strict_types=1);
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
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;
use Shopware\Tax\Struct\TaxBasicStruct;

class ProductBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
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
     * @var string
     */
    protected $manufacturerUuid;

    /**
     * @var string|null
     */
    protected $shippingTime;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $taxUuid;

    /**
     * @var string|null
     */
    protected $mainDetailUuid;

    /**
     * @var int
     */
    protected $pseudoSales;

    /**
     * @var bool
     */
    protected $topseller;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    /**
     * @var int|null
     */
    protected $priceGroupId;

    /**
     * @var string|null
     */
    protected $filterGroupUuid;

    /**
     * @var bool
     */
    protected $lastStock;

    /**
     * @var bool
     */
    protected $notification;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var int
     */
    protected $mode;

    /**
     * @var \DateTime|null
     */
    protected $availableFrom;

    /**
     * @var \DateTime|null
     */
    protected $availableTo;

    /**
     * @var int|null
     */
    protected $configuratorSetId;

    /**
     * @var ProductManufacturerBasicStruct
     */
    protected $manufacturer;
    /**
     * @var ProductDetailBasicStruct
     */
    protected $mainDetail;
    /**
     * @var TaxBasicStruct
     */
    protected $tax;
    /**
     * @var SeoUrlBasicStruct|null
     */
    protected $canonicalUrl;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getManufacturerUuid(): string
    {
        return $this->manufacturerUuid;
    }

    public function setManufacturerUuid(string $manufacturerUuid): void
    {
        $this->manufacturerUuid = $manufacturerUuid;
    }

    public function getShippingTime(): ?string
    {
        return $this->shippingTime;
    }

    public function setShippingTime(?string $shippingTime): void
    {
        $this->shippingTime = $shippingTime;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
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

    public function getTaxUuid(): string
    {
        return $this->taxUuid;
    }

    public function setTaxUuid(string $taxUuid): void
    {
        $this->taxUuid = $taxUuid;
    }

    public function getMainDetailUuid(): ?string
    {
        return $this->mainDetailUuid;
    }

    public function setMainDetailUuid(?string $mainDetailUuid): void
    {
        $this->mainDetailUuid = $mainDetailUuid;
    }

    public function getPseudoSales(): int
    {
        return $this->pseudoSales;
    }

    public function setPseudoSales(int $pseudoSales): void
    {
        $this->pseudoSales = $pseudoSales;
    }

    public function getTopseller(): bool
    {
        return $this->topseller;
    }

    public function setTopseller(bool $topseller): void
    {
        $this->topseller = $topseller;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPriceGroupId(): ?int
    {
        return $this->priceGroupId;
    }

    public function setPriceGroupId(?int $priceGroupId): void
    {
        $this->priceGroupId = $priceGroupId;
    }

    public function getFilterGroupUuid(): ?string
    {
        return $this->filterGroupUuid;
    }

    public function setFilterGroupUuid(?string $filterGroupUuid): void
    {
        $this->filterGroupUuid = $filterGroupUuid;
    }

    public function getLastStock(): bool
    {
        return $this->lastStock;
    }

    public function setLastStock(bool $lastStock): void
    {
        $this->lastStock = $lastStock;
    }

    public function getNotification(): bool
    {
        return $this->notification;
    }

    public function setNotification(bool $notification): void
    {
        $this->notification = $notification;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    public function getAvailableFrom(): ?\DateTime
    {
        return $this->availableFrom;
    }

    public function setAvailableFrom(?\DateTime $availableFrom): void
    {
        $this->availableFrom = $availableFrom;
    }

    public function getAvailableTo(): ?\DateTime
    {
        return $this->availableTo;
    }

    public function setAvailableTo(?\DateTime $availableTo): void
    {
        $this->availableTo = $availableTo;
    }

    public function getConfiguratorSetId(): ?int
    {
        return $this->configuratorSetId;
    }

    public function setConfiguratorSetId(?int $configuratorSetId): void
    {
        $this->configuratorSetId = $configuratorSetId;
    }

    public function getManufacturer(): ProductManufacturerBasicStruct
    {
        return $this->manufacturer;
    }

    public function setManufacturer(ProductManufacturerBasicStruct $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getMainDetail(): ProductDetailBasicStruct
    {
        return $this->mainDetail;
    }

    public function setMainDetail(ProductDetailBasicStruct $mainDetail): void
    {
        $this->mainDetail = $mainDetail;
    }

    public function getTax(): TaxBasicStruct
    {
        return $this->tax;
    }

    public function setTax(TaxBasicStruct $tax): void
    {
        $this->tax = $tax;
    }

    public function getCanonicalUrl(): ?SeoUrlBasicStruct
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?SeoUrlBasicStruct $canonicalUrl): void
    {
        $this->canonicalUrl = $canonicalUrl;
    }
}
