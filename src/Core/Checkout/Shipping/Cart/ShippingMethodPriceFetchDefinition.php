<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart;

use Shopware\Core\Framework\Struct\Struct;

class ShippingMethodPriceFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    private $shippingMethodIds;

    public function __construct(array $shippingMethodIds)
    {
        $this->shippingMethodIds = $shippingMethodIds;
    }

    /**
     * @return string[]
     */
    public function getShippingMethodIds(): array
    {
        return $this->shippingMethodIds;
    }
}
