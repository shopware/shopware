<?php declare(strict_types=1);

namespace Shopware\Payment\Exception;

class InvalidPaymentMethodException extends \Exception
{
    public function __construct(string $token, $code = 0)
    {
        $message = sprintf('The payment method %s has an incomplete configuration and is therefore invalid.', $token);

        parent::__construct($message, $code);
    }
}
