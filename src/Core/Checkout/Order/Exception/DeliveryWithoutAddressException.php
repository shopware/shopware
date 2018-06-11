<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

class DeliveryWithoutAddressException extends \Exception
{
    public const CODE = 4003;

    public function __construct()
    {
        parent::__construct('Delivery contains no shipping address', self::CODE);
    }
}
