<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart;

use Shopware\Core\Framework\Struct\Struct;

class ShippingMethodPriceFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $ruleIds;

    /**
     * @var string[]
     */
    private $shippingMethodIds;

    /**
     * @param string[] $ruleIds
     */
    public function __construct(array $ruleIds, array $shippingMethodIds)
    {
        $this->ruleIds = $ruleIds;
        $this->shippingMethodIds = $shippingMethodIds;
    }

    /**
     * @return string[]
     */
    public function getRuleIds(): array
    {
        return $this->ruleIds;
    }

    /**
     * @return string[]
     */
    public function getShippingMethodIds(): array
    {
        return $this->shippingMethodIds;
    }
}
