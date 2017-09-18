<?php

namespace Shopware\ProductStream\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\ProductStream\Event\ProductStreamBasicLoadedEvent;
use Shopware\ProductStream\Event\ProductStreamWrittenEvent;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductStreamExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductStreamBasicLoadedEvent::NAME => 'productStreamBasicLoaded',
            ProductStreamWrittenEvent::NAME => 'productStreamWritten',
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
        ProductStreamBasicStruct $productStream,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productStreamBasicLoaded(ProductStreamBasicLoadedEvent $event): void
    {
    }

    public function productStreamWritten(ProductStreamWrittenEvent $event): void
    {
    }
}
