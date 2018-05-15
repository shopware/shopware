<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Exception;

class InvalidTransactionException extends \Exception
{
    public function __construct(string $transactionId, $code = 0)
    {
        $message = sprintf('The transaction with id %s is invalid or could not be found.', $transactionId);

        parent::__construct($message, $code);
    }
}
