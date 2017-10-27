<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetailPrice\Event\ProductDetailPriceBasicLoadedEvent;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductDetailPriceFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
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
