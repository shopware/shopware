<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroupDiscount\Event\PriceGroupDiscountBasicLoadedEvent;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class PriceGroupDiscountExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PriceGroupDiscountBasicLoadedEvent::NAME => 'priceGroupDiscountBasicLoaded',
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
        PriceGroupDiscountBasicStruct $priceGroupDiscount,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function priceGroupDiscountBasicLoaded(PriceGroupDiscountBasicLoadedEvent $event): void
    {
    }
}
