<?php

namespace Shopware\Product\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\FactoryExtensionInterface;
use Shopware\Product\Event\ProductBasicLoadedEvent;
use Shopware\Product\Event\ProductDetailLoadedEvent;
use Shopware\Product\Event\ProductWrittenEvent;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductBasicLoadedEvent::NAME => 'productBasicLoaded',
            ProductDetailLoadedEvent::NAME => 'productDetailLoaded',
            ProductWrittenEvent::NAME => 'productWritten',
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
        ProductBasicStruct $product,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productBasicLoaded(ProductBasicLoadedEvent $event): void
    {
    }

    public function productDetailLoaded(ProductDetailLoadedEvent $event): void
    {
    }

    public function productWritten(ProductWrittenEvent $event): void
    {
    }
}
