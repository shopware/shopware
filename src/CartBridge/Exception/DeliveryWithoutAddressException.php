<?php

namespace Shopware\CartBridge\Exception;

class DeliveryWithoutAddressException extends \Exception
{
    public const CODE = 4003;

    public function __construct()
    {
        parent::__construct('Delivery %s contains no shipping address', self::CODE);
    }
}
