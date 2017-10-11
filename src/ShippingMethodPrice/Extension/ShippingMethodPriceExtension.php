<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;

abstract class ShippingMethodPriceExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ShippingMethodPriceBasicLoadedEvent::NAME => 'shippingMethodPriceBasicLoaded',
            
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
        ShippingMethodPriceBasicStruct $shippingMethodPrice,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void
    { }

    public function shippingMethodPriceBasicLoaded(ShippingMethodPriceBasicLoadedEvent $event): void
    { }

    
}