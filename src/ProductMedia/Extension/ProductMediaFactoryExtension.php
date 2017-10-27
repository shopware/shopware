<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductMedia\Event\ProductMediaBasicLoadedEvent;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductMediaFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductMediaBasicLoadedEvent::NAME => 'productMediaBasicLoaded',
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
        ProductMediaBasicStruct $productMedia,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productMediaBasicLoaded(ProductMediaBasicLoadedEvent $event): void
    {
    }
}
