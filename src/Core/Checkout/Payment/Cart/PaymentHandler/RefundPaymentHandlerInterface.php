<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Framework\Context;

/**
 * @package checkout
 */
interface RefundPaymentHandlerInterface extends PaymentHandlerInterface
{
    public function refund(string $refundId, Context $context): void;
}
