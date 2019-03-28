<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Struct\Struct;

class SyncPaymentTransactionStruct extends Struct
{
    /**
     * @var OrderTransactionEntity
     */
    private $orderTransaction;

    public function __construct(OrderTransactionEntity $orderTransaction)
    {
        $this->orderTransaction = $orderTransaction;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }
}
