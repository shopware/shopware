<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\Customer\Struct\CustomerGroupBasicStruct;

class ProductListingPriceBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var float
     */
    protected $price;

    /**
     * @var bool
     */
    protected $displayFromPrice;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
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

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }
}
