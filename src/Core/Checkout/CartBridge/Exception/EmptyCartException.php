<?php declare(strict_types=1);

namespace Shopware\Checkout\CartBridge\Exception;

class EmptyCartException extends \Exception
{
    public const CODE = 4004;

    public function __construct()
    {
        parent::__construct('Cart is empty', self::CODE);
    }
}
