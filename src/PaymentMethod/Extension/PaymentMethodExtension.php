<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodDetailLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodWrittenEvent;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class PaymentMethodExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PaymentMethodBasicLoadedEvent::NAME => 'paymentMethodBasicLoaded',
            PaymentMethodDetailLoadedEvent::NAME => 'paymentMethodDetailLoaded',
            PaymentMethodWrittenEvent::NAME => 'paymentMethodWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getDetailFields(): array
    {
        return [];
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        PaymentMethodBasicStruct $paymentMethod,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function paymentMethodBasicLoaded(PaymentMethodBasicLoadedEvent $event): void
    {
    }

    public function paymentMethodDetailLoaded(PaymentMethodDetailLoadedEvent $event): void
    {
    }

    public function paymentMethodWritten(PaymentMethodWrittenEvent $event): void
    {
    }
}
