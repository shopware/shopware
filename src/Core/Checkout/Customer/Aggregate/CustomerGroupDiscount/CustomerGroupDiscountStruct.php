<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupStruct;
use Shopware\Core\Framework\ORM\Entity;

class CustomerGroupDiscountStruct extends Entity
{
    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var float
     */
    protected $percentageDiscount;

    /**
     * @var float
     */
    protected $minimumCartAmount;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CustomerGroupStruct|null
     */
    protected $customerGroup;

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getPercentageDiscount(): float
    {
        return $this->percentageDiscount;
    }

    public function setPercentageDiscount(float $percentageDiscount): void
    {
        $this->percentageDiscount = $percentageDiscount;
    }

    public function getMinimumCartAmount(): float
    {
        return $this->minimumCartAmount;
    }

    public function setMinimumCartAmount(float $minimumCartAmount): void
    {
        $this->minimumCartAmount = $minimumCartAmount;
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

    public function getCustomerGroup(): ?CustomerGroupStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }
}
