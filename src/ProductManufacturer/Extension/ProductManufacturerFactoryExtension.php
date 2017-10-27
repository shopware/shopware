<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductManufacturerFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductManufacturerBasicLoadedEvent::NAME => 'productManufacturerBasicLoaded',
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
        ProductManufacturerBasicStruct $productManufacturer,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productManufacturerBasicLoaded(ProductManufacturerBasicLoadedEvent $event): void
    {
    }
}
