<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class AsyncPaymentTransactionStruct extends SyncPaymentTransactionStruct
{
    /**
     * @deprecated tag:v6.6.0 - Will be strongly typed
     *
     * @var string
     */
    protected $returnUrl;

    public function __construct(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        string $returnUrl,
        protected ?RecurringDataStruct $recurringData = null
    ) {
        parent::__construct($orderTransaction, $order, $recurringData);

        $this->returnUrl = $returnUrl;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
