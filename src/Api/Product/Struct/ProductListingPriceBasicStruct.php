<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Entity\Entity;

class ProductListingPriceBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var float
     */
    protected $sortingPrice;

    /**
     * @var float
     */
    protected $price;

    /**
     * @var bool
     */
    protected $displayFromPrice;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getSortingPrice(): float
    {
        return $this->sortingPrice;
    }

    public function setSortingPrice(float $sortingPrice): void
    {
        $this->sortingPrice = $sortingPrice;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getDisplayFromPrice(): bool
    {
        return $this->displayFromPrice;
    }

    public function setDisplayFromPrice(bool $displayFromPrice): void
    {
        $this->displayFromPrice = $displayFromPrice;
    }

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }
}
