<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Struct;

use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;

class CustomerGroupDetailStruct extends CustomerGroupBasicStruct
{
    /**
     * @var string[]
     */
    protected $discountUuids = [];

    /**
     * @var CustomerGroupDiscountBasicCollection
     */
    protected $discounts;

    public function __construct()
    {
        $this->discounts = new CustomerGroupDiscountBasicCollection();
    }

    public function getDiscountUuids(): array
    {
        return $this->discountUuids;
    }

    public function setDiscountUuids(array $discountUuids): void
    {
        $this->discountUuids = $discountUuids;
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->discounts;
    }

    public function setDiscounts(CustomerGroupDiscountBasicCollection $discounts): void
    {
        $this->discounts = $discounts;
    }
}
