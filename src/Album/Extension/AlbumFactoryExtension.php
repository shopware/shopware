<?php declare(strict_types=1);

namespace Shopware\Album\Extension;

use Shopware\Album\Event\AlbumBasicLoadedEvent;
use Shopware\Album\Event\AlbumDetailLoadedEvent;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AlbumFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AlbumBasicLoadedEvent::NAME => 'albumBasicLoaded',
            AlbumDetailLoadedEvent::NAME => 'albumDetailLoaded',
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
        AlbumBasicStruct $album,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function albumBasicLoaded(AlbumBasicLoadedEvent $event): void
    {
    }

    public function albumDetailLoaded(AlbumDetailLoadedEvent $event): void
    {
    }
}
