<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Struct;

use Shopware\Framework\Struct\Struct;

class CustomerGroupDiscountBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var float
     */
    protected $percentageDiscount;

    /**
     * @var float
     */
    protected $minimumCartAmount;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
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
}
