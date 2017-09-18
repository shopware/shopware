<?php

namespace Shopware\AreaCountry\Extension;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryDetailLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryWrittenEvent;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AreaCountryExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AreaCountryBasicLoadedEvent::NAME => 'areaCountryBasicLoaded',
            AreaCountryDetailLoadedEvent::NAME => 'areaCountryDetailLoaded',
            AreaCountryWrittenEvent::NAME => 'areaCountryWritten',
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
        AreaCountryBasicStruct $areaCountry,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function areaCountryBasicLoaded(AreaCountryBasicLoadedEvent $event): void
    {
    }

    public function areaCountryDetailLoaded(AreaCountryDetailLoadedEvent $event): void
    {
    }

    public function areaCountryWritten(AreaCountryWrittenEvent $event): void
    {
    }
}
