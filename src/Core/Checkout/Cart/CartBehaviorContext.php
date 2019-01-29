<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

class CartBehaviorContext extends Struct
{
    /**
     * @var bool By default, a new cart with new deliveries is created during each calculation. This behavior
     *           can be changed e.g. for recalculations
     */
    protected $buildDeliveries = true;

    public function shouldBuildDeliveries(): bool
    {
        return $this->buildDeliveries;
    }

    public function setBuildDeliveries(bool $buildDeliveries): CartBehaviorContext
    {
        $this->buildDeliveries = $buildDeliveries;

        return $this;
    }
}
