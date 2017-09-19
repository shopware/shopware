<?php

namespace Shopware\Album\Extension;

use Shopware\Album\Event\AlbumBasicLoadedEvent;
use Shopware\Album\Event\AlbumDetailLoadedEvent;
use Shopware\Album\Event\AlbumWrittenEvent;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AlbumExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AlbumBasicLoadedEvent::NAME => 'albumBasicLoaded',
            AlbumDetailLoadedEvent::NAME => 'albumDetailLoaded',
            AlbumWrittenEvent::NAME => 'albumWritten',
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

    public function albumWritten(AlbumWrittenEvent $event): void
    {
    }
}
