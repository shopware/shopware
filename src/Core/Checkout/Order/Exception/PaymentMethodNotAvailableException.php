<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed. Use OrderException::paymentMethodNotAvailable() instead
 */
#[Package('checkout')]
class PaymentMethodNotAvailableException extends OrderException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'CHECKOUT__UNAVAILABLE_PAYMENT_METHOD',
            'The order has no active payment method - {{ id }}',
            ['id' => $id]
        );
    }
}
