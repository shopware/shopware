<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

class CartBehavior extends Struct
{
    /**
     * @var bool
     */
    protected $isRecalculation = false;

    /**
     * @var bool
     */
    protected $isAdminOrder = false;

    public function isRecalculation(): bool
    {
        return $this->isRecalculation;
    }

    public function setIsRecalculation(bool $isRecalculation): CartBehavior
    {
        $this->isRecalculation = $isRecalculation;

        return $this;
    }

    public function isAdminOrder(): bool
    {
        return $this->isAdminOrder;
    }

    public function setIsAdminOrder(bool $isAdminOrder): void
    {
        $this->isAdminOrder = $isAdminOrder;
    }
}
