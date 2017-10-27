<?php declare(strict_types=1);

namespace Shopware\Category\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Category\Event\CategoryDetailLoadedEvent;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CategoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CategoryBasicLoadedEvent::NAME => 'categoryBasicLoaded',
            CategoryDetailLoadedEvent::NAME => 'categoryDetailLoaded',
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
}
