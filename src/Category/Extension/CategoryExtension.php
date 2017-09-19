<?php

namespace Shopware\Category\Extension;

use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Category\Event\CategoryDetailLoadedEvent;
use Shopware\Category\Event\CategoryWrittenEvent;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CategoryExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CategoryBasicLoadedEvent::NAME => 'categoryBasicLoaded',
            CategoryDetailLoadedEvent::NAME => 'categoryDetailLoaded',
            CategoryWrittenEvent::NAME => 'categoryWritten',
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
        CategoryBasicStruct $category,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function categoryBasicLoaded(CategoryBasicLoadedEvent $event): void
    {
    }

    public function categoryDetailLoaded(CategoryDetailLoadedEvent $event): void
    {
    }

    public function categoryWritten(CategoryWrittenEvent $event): void
    {
    }
}
