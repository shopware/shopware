<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ListingSorting\Event\ListingSortingBasicLoadedEvent;
use Shopware\ListingSorting\Event\ListingSortingWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;

abstract class ListingSortingExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ListingSortingBasicLoadedEvent::NAME => 'listingSortingBasicLoaded',
            
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
        ListingSortingBasicStruct $listingSorting,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void
    { }

    public function listingSortingBasicLoaded(ListingSortingBasicLoadedEvent $event): void
    { }

    
}