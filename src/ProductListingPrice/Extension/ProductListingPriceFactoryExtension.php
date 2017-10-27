<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Event\ProductListingPriceBasicLoadedEvent;
use Shopware\ProductListingPrice\Event\ProductListingPriceWrittenEvent;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductListingPriceFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductListingPriceBasicLoadedEvent::NAME => 'productListingPriceBasicLoaded',
            ProductListingPriceWrittenEvent::NAME => 'productListingPriceWritten',
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
        ProductListingPriceBasicStruct $productListingPrice,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productListingPriceBasicLoaded(ProductListingPriceBasicLoadedEvent $event): void
    {
    }

    public function productListingPriceWritten(ProductListingPriceWrittenEvent $event): void
    {
    }
}
