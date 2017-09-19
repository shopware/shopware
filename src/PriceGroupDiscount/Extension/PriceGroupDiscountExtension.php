<?php

namespace Shopware\PriceGroupDiscount\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\PriceGroupDiscount\Event\PriceGroupDiscountBasicLoadedEvent;
use Shopware\PriceGroupDiscount\Event\PriceGroupDiscountWrittenEvent;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class PriceGroupDiscountExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PriceGroupDiscountBasicLoadedEvent::NAME => 'priceGroupDiscountBasicLoaded',
            PriceGroupDiscountWrittenEvent::NAME => 'priceGroupDiscountWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        PriceGroupDiscountBasicStruct $priceGroupDiscount,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function priceGroupDiscountBasicLoaded(PriceGroupDiscountBasicLoadedEvent $event): void
    {
    }

    public function priceGroupDiscountWritten(PriceGroupDiscountWrittenEvent $event): void
    {
    }
}
