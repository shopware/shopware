<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Extension;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryDetailLoadedEvent;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AreaCountryExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AreaCountryBasicLoadedEvent::NAME => 'areaCountryBasicLoaded',
            AreaCountryDetailLoadedEvent::NAME => 'areaCountryDetailLoaded',
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
}
