<?php declare(strict_types=1);

namespace Shopware\CartBridge\Exception;

class CustomerHasNoActiveBillingAddressException extends \Exception
{
    public const CODE = 4002;

    public function __construct(string $customerId)
    {
        parent::__construct(sprintf('Customer %s has no active billing address id', $customerId), self::CODE);
    }
}
