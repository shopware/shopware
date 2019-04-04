<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

class CartBehavior extends Struct
{
    /**
     * @var bool By default, a new cart with new deliveries is created during each calculation. This behavior
     *           can be changed e.g. for recalculations
     */
    protected $buildDeliveries = true;

    /**
     * @var bool
     */
    protected $isRecalculation = false;

    public function isRecalculation(): bool
    {
        return $this->isRecalculation;
    }

    public function setIsRecalculation(bool $isRecalculation): CartBehavior
    {
        $this->isRecalculation = $isRecalculation;

        return $this;
    }

    public function shouldBuildDeliveries(): bool
    {
        return $this->buildDeliveries;
    }

    public function setBuildDeliveries(bool $buildDeliveries): CartBehavior
    {
        $this->buildDeliveries = $buildDeliveries;

        return $this;
    }
}
