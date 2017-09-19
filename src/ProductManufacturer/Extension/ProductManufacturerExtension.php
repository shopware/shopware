<?php

namespace Shopware\ProductManufacturer\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\ProductManufacturer\Event\ProductManufacturerWrittenEvent;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductManufacturerExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductManufacturerBasicLoadedEvent::NAME => 'productManufacturerBasicLoaded',
            ProductManufacturerWrittenEvent::NAME => 'productManufacturerWritten',
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
        ProductManufacturerBasicStruct $productManufacturer,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productManufacturerBasicLoaded(ProductManufacturerBasicLoadedEvent $event): void
    {
    }

    public function productManufacturerWritten(ProductManufacturerWrittenEvent $event): void
    {
    }
}
