<?php declare(strict_types=1);

namespace Shopware\ProductStream\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductStream\Event\ProductStreamBasicLoadedEvent;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductStreamExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductStreamBasicLoadedEvent::NAME => 'productStreamBasicLoaded',
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
        ProductStreamBasicStruct $productStream,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productStreamBasicLoaded(ProductStreamBasicLoadedEvent $event): void
    {
    }
}
