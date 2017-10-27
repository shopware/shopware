<?php declare(strict_types=1);

namespace Shopware\Media\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\Media\Struct\MediaBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class MediaFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            MediaBasicLoadedEvent::NAME => 'mediaBasicLoaded',
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
        MediaBasicStruct $media,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function mediaBasicLoaded(MediaBasicLoadedEvent $event): void
    {
    }
}
