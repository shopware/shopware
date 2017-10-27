<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupDetailLoadedEvent;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class PriceGroupExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PriceGroupBasicLoadedEvent::NAME => 'priceGroupBasicLoaded',
            PriceGroupDetailLoadedEvent::NAME => 'priceGroupDetailLoaded',
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
        PriceGroupBasicStruct $priceGroup,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function priceGroupBasicLoaded(PriceGroupBasicLoadedEvent $event): void
    {
    }

    public function priceGroupDetailLoaded(PriceGroupDetailLoadedEvent $event): void
    {
    }
}
