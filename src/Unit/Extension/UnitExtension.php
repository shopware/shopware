<?php declare(strict_types=1);

namespace Shopware\Unit\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Unit\Event\UnitBasicLoadedEvent;
use Shopware\Unit\Struct\UnitBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class UnitExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            UnitBasicLoadedEvent::NAME => 'unitBasicLoaded',
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
        UnitBasicStruct $unit,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function unitBasicLoaded(UnitBasicLoadedEvent $event): void
    {
    }
}
