<?php

namespace Shopware\Tax\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Tax\Event\TaxBasicLoadedEvent;
use Shopware\Tax\Event\TaxWrittenEvent;
use Shopware\Tax\Struct\TaxBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class TaxExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TaxBasicLoadedEvent::NAME => 'taxBasicLoaded',
            TaxWrittenEvent::NAME => 'taxWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
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
    ): void {
    }

    public function taxBasicLoaded(TaxBasicLoadedEvent $event): void
    {
    }

    public function taxWritten(TaxWrittenEvent $event): void
    {
    }
}
