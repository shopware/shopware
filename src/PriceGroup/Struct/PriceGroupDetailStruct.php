<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Struct;

use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;

class PriceGroupDetailStruct extends PriceGroupBasicStruct
{
    /**
     * @var PriceGroupDiscountBasicCollection
     */
    protected $discounts;

    public function __construct()
    {
        $this->discounts = new PriceGroupDiscountBasicCollection();
    }

    public function getDiscounts(): PriceGroupDiscountBasicCollection
    {
        return $this->discounts;
    }

    public function setDiscounts(PriceGroupDiscountBasicCollection $discounts): void
    {
        $this->discounts = $discounts;
    }
}
