<?php

namespace Shopware\Unit\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Unit\Event\UnitBasicLoadedEvent;
use Shopware\Unit\Event\UnitWrittenEvent;
use Shopware\Unit\Struct\UnitBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class UnitExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            UnitBasicLoadedEvent::NAME => 'unitBasicLoaded',
            UnitWrittenEvent::NAME => 'unitWritten',
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
        UnitBasicStruct $unit,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function unitBasicLoaded(UnitBasicLoadedEvent $event): void
    {
    }

    public function unitWritten(UnitWrittenEvent $event): void
    {
    }
}
