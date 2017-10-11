<?php declare(strict_types=1);

namespace Shopware\Tax\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Tax\Event\TaxBasicLoadedEvent;
use Shopware\Tax\Event\TaxWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Tax\Struct\TaxBasicStruct;

abstract class TaxExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TaxBasicLoadedEvent::NAME => 'taxBasicLoaded',
            
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
        TaxBasicStruct $tax,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void
    { }

    public function taxBasicLoaded(TaxBasicLoadedEvent $event): void
    { }

    
}