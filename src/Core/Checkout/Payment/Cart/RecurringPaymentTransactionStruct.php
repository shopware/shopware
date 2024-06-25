<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - will be removed, PaymentTransactionStruct with new payment handlers instead
 */
#[Package('checkout')]
class RecurringPaymentTransactionStruct extends SyncPaymentTransactionStruct
{
}
