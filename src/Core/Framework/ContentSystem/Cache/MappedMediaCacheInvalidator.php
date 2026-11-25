<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Cache;

use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates only Content System renders that recorded a dependency on the changed media.
 * DAL events also cover translations and writes that do not run the media indexer.
 *
 * @internal
 */
#[Package('framework')]
class MappedMediaCacheInvalidator implements EventSubscriberInterface
{
    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public static function buildName(string $mediaId): string
    {
        return 'content-system-mapped-media-' . $mediaId;
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'invalidateWritten',
            MediaIndexerEvent::class => 'invalidateIndexed',
        ];
    }

    /**
     * @param EntityWrittenContainerEvent<string|array<string, string>> $event
     */
    public function invalidateWritten(EntityWrittenContainerEvent $event): void
    {
        $mediaIds = array_filter($event->getPrimaryKeys(MediaDefinition::ENTITY_NAME), '\is_string');

        foreach ($event->getPrimaryKeys(MediaTranslationDefinition::ENTITY_NAME) as $key) {
            if (\is_array($key) && isset($key['mediaId'])) {
                $mediaIds[] = $key['mediaId'];
            }
        }

        $this->invalidate($mediaIds);
    }

    public function invalidateIndexed(MediaIndexerEvent $event): void
    {
        $this->invalidate($event->getIds());
    }

    /**
     * @param array<string> $mediaIds
     */
    private function invalidate(array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(self::buildName(...), array_values(array_unique($mediaIds))));
    }
}
