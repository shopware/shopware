<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Struct;

use Shopware\Framework\Struct\Struct;

class PriceGroupDiscountBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $priceGroupUuid;

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
    protected $productCount;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getPriceGroupUuid(): string
    {
        return $this->priceGroupUuid;
    }

    public function setPriceGroupUuid(string $priceGroupUuid): void
    {
        $this->priceGroupUuid = $priceGroupUuid;
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

    public function getProductCount(): float
    {
        return $this->productCount;
    }

    public function setProductCount(float $productCount): void
    {
        $this->productCount = $productCount;
    }
}
