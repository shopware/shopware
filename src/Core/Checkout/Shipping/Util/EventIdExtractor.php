<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Util;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodRules\ShippingMethodRuleDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class EventIdExtractor
{
    public function getShippingMethodIds(EntityWrittenContainerEvent $generic): array
    {
        $ids = [];

        $event = $generic->getEventByDefinition(ShippingMethodDefinition::class);
        if ($event) {
            $ids = $event->getIds();
        }

        $event = $generic->getEventByDefinition(ShippingMethodRuleDefinition::class);
        if ($event) {
            foreach ($event->getPayloads() as $id) {
                if (isset($id['shippingMethodId'])) {
                    $ids[] = $id['shippingMethodId'];
                }
            }
        }

        return $ids;
    }
}
