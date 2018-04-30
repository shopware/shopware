<?php declare(strict_types=1);

namespace Shopware\Payment\Exception;

class InvalidTokenException extends \Exception
{
    public function __construct(string $token, $code = 0)
    {
        $message = sprintf('The provided token %s is invalid and the payment could not be processed.', $token);

        parent::__construct($message, $code);
    }
}
