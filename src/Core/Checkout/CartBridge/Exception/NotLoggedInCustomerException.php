<?php declare(strict_types=1);

namespace Shopware\Checkout\CartBridge\Exception;

class NotLoggedInCustomerException extends \Exception
{
    public const CODE = 4005;

    public function __construct()
    {
        parent::__construct('No logged in customer detected', self::CODE);
    }
}
