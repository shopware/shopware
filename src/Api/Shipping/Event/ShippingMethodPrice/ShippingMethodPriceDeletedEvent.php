<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethodPrice;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shipping\Definition\ShippingMethodPriceDefinition;

class ShippingMethodPriceDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shipping_method_price.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodPriceDefinition::class;
    }
}
