<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Struct\Struct;

class PaymentTransactionStruct extends Struct
{
    /**
     * @var OrderTransactionEntity
     */
    private $orderTransaction;

    /**
     * @var string
     */
    private $returnUrl;

    public function __construct(OrderTransactionEntity $orderTransaction, string $returnUrl)
    {
        $this->orderTransaction = $orderTransaction;
        $this->returnUrl = $returnUrl;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
