<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ProductListingPrice\Event\ProductListingPriceBasicLoadedEvent;
use Shopware\ProductListingPrice\Event\ProductListingPriceWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;

abstract class ProductListingPriceExtension implements ExtensionInterface, EventSubscriberInterface
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
    ): void
    { }

    public function productListingPriceBasicLoaded(ProductListingPriceBasicLoadedEvent $event): void
    { }

    public function productListingPriceWritten(ProductListingPriceWrittenEvent $event): void
{ }
}