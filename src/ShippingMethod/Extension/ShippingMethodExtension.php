<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodDetailLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

abstract class ShippingMethodExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ShippingMethodBasicLoadedEvent::NAME => 'shippingMethodBasicLoaded',
            ShippingMethodDetailLoadedEvent::NAME => 'shippingMethodDetailLoaded',
            
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
    ): void
    { }

    public function shippingMethodBasicLoaded(ShippingMethodBasicLoadedEvent $event): void
    { }

    public function shippingMethodDetailLoaded(ShippingMethodDetailLoadedEvent $event): void
    { }

    

}