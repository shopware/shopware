<?php

namespace Shopware\ProductPrice\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\FactoryExtensionInterface;
use Shopware\ProductPrice\Event\ProductPriceBasicLoadedEvent;
use Shopware\ProductPrice\Event\ProductPriceWrittenEvent;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductPriceExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductPriceBasicLoadedEvent::NAME => 'productPriceBasicLoaded',
            ProductPriceWrittenEvent::NAME => 'productPriceWritten',
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
        ProductPriceBasicStruct $productPrice,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productPriceBasicLoaded(ProductPriceBasicLoadedEvent $event): void
    {
    }

    public function productPriceWritten(ProductPriceWrittenEvent $event): void
    {
    }
}
