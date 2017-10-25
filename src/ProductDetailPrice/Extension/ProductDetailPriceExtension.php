<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionInterface;
use Shopware\ProductDetailPrice\Event\ProductDetailPriceBasicLoadedEvent;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductDetailPriceExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductDetailPriceBasicLoadedEvent::NAME => 'productDetailPriceBasicLoaded',
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
        ProductDetailPriceBasicStruct $productDetailPrice,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productDetailPriceBasicLoaded(ProductDetailPriceBasicLoadedEvent $event): void
    {
    }
}
