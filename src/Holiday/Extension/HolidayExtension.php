<?php declare(strict_types=1);

namespace Shopware\Holiday\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Event\HolidayBasicLoadedEvent;
use Shopware\Holiday\Struct\HolidayBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class HolidayExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            HolidayBasicLoadedEvent::NAME => 'holidayBasicLoaded',
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
        HolidayBasicStruct $holiday,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function holidayBasicLoaded(HolidayBasicLoadedEvent $event): void
    {
    }
}
