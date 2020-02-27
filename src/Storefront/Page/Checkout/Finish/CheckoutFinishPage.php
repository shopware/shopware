<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Page;

class CheckoutFinishPage extends Page
{
    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var bool
     */
    protected $changedPayment = false;

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function isChangedPayment(): bool
    {
        return $this->changedPayment;
    }

    public function setChangedPayment(bool $changedPayment): void
    {
        $this->changedPayment = $changedPayment;
    }
}
