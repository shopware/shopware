<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class PaymentDistinguishableNameSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addDistinguishablePaymentName',
        ];
    }

    public function addDistinguishablePaymentName(EntityLoadedEvent $event): void
    {
        /** @var PaymentMethodEntity $payment */
        foreach ($event->getEntities() as $payment) {
            if ($payment->getTranslation('distinguishableName') === null) {
                $payment->addTranslated('distinguishableName', $payment->getTranslation('name'));
            }
            if ($payment->getDistinguishableName() === null) {
                $payment->setDistinguishableName($payment->getName());
            }
        }
    }
}
