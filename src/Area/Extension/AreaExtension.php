<?php declare(strict_types=1);

namespace Shopware\Area\Extension;

use Shopware\Area\Event\AreaBasicLoadedEvent;
use Shopware\Area\Event\AreaDetailLoadedEvent;
use Shopware\Area\Event\AreaWrittenEvent;
use Shopware\Area\Struct\AreaBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AreaExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AreaBasicLoadedEvent::NAME => 'areaBasicLoaded',
            AreaDetailLoadedEvent::NAME => 'areaDetailLoaded',
            AreaWrittenEvent::NAME => 'areaWritten',
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
        AreaBasicStruct $area,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function areaBasicLoaded(AreaBasicLoadedEvent $event): void
    {
    }

    public function areaDetailLoaded(AreaDetailLoadedEvent $event): void
    {
    }

    public function areaWritten(AreaWrittenEvent $event): void
    {
    }
}
