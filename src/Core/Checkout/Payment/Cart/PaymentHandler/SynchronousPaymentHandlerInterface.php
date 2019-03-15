<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;

interface SynchronousPaymentHandlerInterface
{
    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     */
    public function pay(SyncPaymentTransactionStruct $transaction, Context $context): void;
}
