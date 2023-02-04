<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class AsyncPaymentTransactionStruct extends SyncPaymentTransactionStruct
{
    /**
     * @var string
     */
    protected $returnUrl;

    public function __construct(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        string $returnUrl
    ) {
        parent::__construct($orderTransaction, $order);
        $this->returnUrl = $returnUrl;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
