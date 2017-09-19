<?php

namespace Shopware\PriceGroup\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\FactoryExtensionInterface;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupDetailLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupWrittenEvent;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class PriceGroupExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PriceGroupBasicLoadedEvent::NAME => 'priceGroupBasicLoaded',
            PriceGroupDetailLoadedEvent::NAME => 'priceGroupDetailLoaded',
            PriceGroupWrittenEvent::NAME => 'priceGroupWritten',
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

    public function priceGroupWritten(PriceGroupWrittenEvent $event): void
    {
    }
}
