<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ShippingMethodPriceFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
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
    ): void {
    }

    public function shippingMethodPriceBasicLoaded(ShippingMethodPriceBasicLoadedEvent $event): void
    {
    }
}
