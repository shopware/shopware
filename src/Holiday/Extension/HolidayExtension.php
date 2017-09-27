<?php declare(strict_types=1);

namespace Shopware\Holiday\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Holiday\Event\HolidayBasicLoadedEvent;
use Shopware\Holiday\Event\HolidayWrittenEvent;
use Shopware\Holiday\Struct\HolidayBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class HolidayExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            HolidayBasicLoadedEvent::NAME => 'holidayBasicLoaded',
            HolidayWrittenEvent::NAME => 'holidayWritten',
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

    public function holidayWritten(HolidayWrittenEvent $event): void
    {
    }
}
