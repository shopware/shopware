<?php

namespace Shopware\Media\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\Media\Event\MediaWrittenEvent;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class MediaExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            MediaBasicLoadedEvent::NAME => 'mediaBasicLoaded',
            MediaWrittenEvent::NAME => 'mediaWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        MediaBasicStruct $media,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function mediaBasicLoaded(MediaBasicLoadedEvent $event): void
    {
    }

    public function mediaWritten(MediaWrittenEvent $event): void
    {
    }
}
