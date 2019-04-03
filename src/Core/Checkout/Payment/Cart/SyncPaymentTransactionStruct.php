<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\Struct;

class SyncPaymentTransactionStruct extends Struct
{
    /**
     * @var OrderTransactionEntity
     */
    private $orderTransaction;

    /**
     * @var OrderEntity
     */
    private $order;

    public function __construct(OrderTransactionEntity $orderTransaction, OrderEntity $order)
    {
        $this->orderTransaction = $orderTransaction;
        $this->order = $order;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }
}
