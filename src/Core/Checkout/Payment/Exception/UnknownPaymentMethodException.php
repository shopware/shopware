<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class UnknownPaymentMethodException extends \Exception
{
    public function __construct(string $token, $code = 0)
    {
        $message = sprintf('The payment method %s could not be found.', $token);

        parent::__construct($message, $code);
    }
}
