<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Util;

use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodRules\PaymentMethodRuleDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class EventIdExtractor
{
    public function getPaymentMethodIds(EntityWrittenContainerEvent $containerEvent): array
    {
        $ids = [];

        $event = $containerEvent->getEventByDefinition(PaymentMethodDefinition::class);
        if ($event) {
            $ids = $event->getIds();
        }

        $event = $containerEvent->getEventByDefinition(PaymentMethodRuleDefinition::class);
        if ($event) {
            foreach ($event->getPayloads() as $id) {
                if (isset($id['paymentMethodId'])) {
                    $ids[] = $id['paymentMethodId'];
                }
            }
        }

        return $ids;
    }
}
