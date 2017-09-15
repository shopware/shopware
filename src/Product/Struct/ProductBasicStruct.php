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

use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Framework\Struct\Struct;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;
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
    protected $taxUuid;

    /**
     * @var string
     */
    protected $manufacturerUuid;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $pseudoSales;

    /**
     * @var bool
     */
    protected $markAsTopseller;

    /**
     * @var string|null
     */
    protected $priceGroupUuid;

    /**
     * @var string|null
     */
    protected $filterGroupUuid;

    /**
     * @var bool
     */
    protected $isCloseout;

    /**
     * @var bool
     */
    protected $allowNotification;

    /**
     * @var string|null
     */
    protected $template;

    /**
     * @var int|null
     */
    protected $configuratorSetId;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string
     */
    protected $mainDetailUuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $keywords;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $descriptionLong;

    /**
     * @var string|null
     */
    protected $metaTitle;

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

    /**
     * @var PriceGroupBasicStruct|null
     */
    protected $priceGroup;

    /**
     * @var string[]
     */
    protected $blockedCustomerGroupsUuids = [];

    /**
     * @var CustomerGroupBasicCollection
     */
    protected $blockedCustomerGroupss;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getTaxUuid(): string
    {
        return $this->taxUuid;
    }

    public function setTaxUuid(string $taxUuid): void
    {
        $this->taxUuid = $taxUuid;
    }

    public function getManufacturerUuid(): string
    {
        return $this->manufacturerUuid;
    }

    public function setManufacturerUuid(string $manufacturerUuid): void
    {
        $this->manufacturerUuid = $manufacturerUuid;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPseudoSales(): int
    {
        return $this->pseudoSales;
    }

    public function setPseudoSales(int $pseudoSales): void
    {
        $this->pseudoSales = $pseudoSales;
    }

    public function getMarkAsTopseller(): bool
    {
        return $this->markAsTopseller;
    }

    public function setMarkAsTopseller(bool $markAsTopseller): void
    {
        $this->markAsTopseller = $markAsTopseller;
    }

    public function getPriceGroupUuid(): ?string
    {
        return $this->priceGroupUuid;
    }

    public function setPriceGroupUuid(?string $priceGroupUuid): void
    {
        $this->priceGroupUuid = $priceGroupUuid;
    }

    public function getFilterGroupUuid(): ?string
    {
        return $this->filterGroupUuid;
    }

    public function setFilterGroupUuid(?string $filterGroupUuid): void
    {
        $this->filterGroupUuid = $filterGroupUuid;
    }

    public function getIsCloseout(): bool
    {
        return $this->isCloseout;
    }

    public function setIsCloseout(bool $isCloseout): void
    {
        $this->isCloseout = $isCloseout;
    }

    public function getAllowNotification(): bool
    {
        return $this->allowNotification;
    }

    public function setAllowNotification(bool $allowNotification): void
    {
        $this->allowNotification = $allowNotification;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function getConfiguratorSetId(): ?int
    {
        return $this->configuratorSetId;
    }

    public function setConfiguratorSetId(?int $configuratorSetId): void
    {
        $this->configuratorSetId = $configuratorSetId;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
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

    public function getMainDetailUuid(): string
    {
        return $this->mainDetailUuid;
    }

    public function setMainDetailUuid(string $mainDetailUuid): void
    {
        $this->mainDetailUuid = $mainDetailUuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getKeywords(): string
    {
        return $this->keywords;
    }

    public function setKeywords(string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescriptionLong(): string
    {
        return $this->descriptionLong;
    }

    public function setDescriptionLong(string $descriptionLong): void
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

    public function getPriceGroup(): ?PriceGroupBasicStruct
    {
        return $this->priceGroup;
    }

    public function setPriceGroup(?PriceGroupBasicStruct $priceGroup): void
    {
        $this->priceGroup = $priceGroup;
    }

    public function getBlockedCustomerGroupsUuids(): array
    {
        return $this->blockedCustomerGroupsUuids;
    }

    public function setBlockedCustomerGroupsUuids(array $blockedCustomerGroupsUuids): void
    {
        $this->blockedCustomerGroupsUuids = $blockedCustomerGroupsUuids;
    }

    public function getBlockedCustomerGroupss(): CustomerGroupBasicCollection
    {
        return $this->blockedCustomerGroupss;
    }

    public function setBlockedCustomerGroupss(CustomerGroupBasicCollection $blockedCustomerGroupss): void
    {
        $this->blockedCustomerGroupss = $blockedCustomerGroupss;
    }
}
