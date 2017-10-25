<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Extension;

use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AreaCountryStateExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AreaCountryStateBasicLoadedEvent::NAME => 'areaCountryStateBasicLoaded',
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
        AreaCountryStateBasicStruct $areaCountryState,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function areaCountryStateBasicLoaded(AreaCountryStateBasicLoadedEvent $event): void
    {
    }
}
