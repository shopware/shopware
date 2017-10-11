<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ProductMedia\Event\ProductMediaBasicLoadedEvent;
use Shopware\ProductMedia\Event\ProductMediaWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

abstract class ProductMediaExtension implements ExtensionInterface, EventSubscriberInterface
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
    ): void
    { }

    public function productMediaBasicLoaded(ProductMediaBasicLoadedEvent $event): void
    { }

    
}