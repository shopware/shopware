<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Event\ProductDetailBasicLoadedEvent;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductDetailFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductDetailBasicLoadedEvent::NAME => 'productDetailBasicLoaded',
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
        ProductDetailBasicStruct $productDetail,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productDetailBasicLoaded(ProductDetailBasicLoadedEvent $event): void
    {
    }
}
