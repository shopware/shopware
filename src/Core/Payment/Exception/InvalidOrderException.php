<?php declare(strict_types=1);

namespace Shopware\Payment\Exception;

class InvalidOrderException extends \Exception
{
    public function __construct(string $orderId, $code = 0)
    {
        $message = sprintf('The order with id %s is invalid or could not be found.', $orderId);

        parent::__construct($message, $code);
    }
}
