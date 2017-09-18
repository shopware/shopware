<?php

namespace Shopware\ShippingMethod\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodDetailLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodWrittenEvent;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ShippingMethodExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ShippingMethodBasicLoadedEvent::NAME => 'shippingMethodBasicLoaded',
            ShippingMethodDetailLoadedEvent::NAME => 'shippingMethodDetailLoaded',
            ShippingMethodWrittenEvent::NAME => 'shippingMethodWritten',
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
        ShippingMethodBasicStruct $shippingMethod,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function shippingMethodBasicLoaded(ShippingMethodBasicLoadedEvent $event): void
    {
    }

    public function shippingMethodDetailLoaded(ShippingMethodDetailLoadedEvent $event): void
    {
    }

    public function shippingMethodWritten(ShippingMethodWrittenEvent $event): void
    {
    }
}
