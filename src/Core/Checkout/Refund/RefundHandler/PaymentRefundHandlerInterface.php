<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\RefundHandler;

use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundEntity;
use Shopware\Core\Checkout\Refund\Exception\PaymentRefundProcessException;
use Shopware\Core\Framework\Context;

interface PaymentRefundHandlerInterface
{
    /**
     * The refund method will be called after a @see OrderRefundEntity was created via the administration or the api.
     * Allows to process the order refund and store additional information.
     *
     * Throw a @see PaymentRefundProcessException exception if an error ocurres while processing the refund
     *
     * @throws PaymentRefundProcessException
     */
    public function refund(string $orderRefundId, Context $context): void;
}
