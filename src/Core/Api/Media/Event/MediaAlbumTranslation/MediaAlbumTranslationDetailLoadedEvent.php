<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbumTranslation;

use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Api\Media\Collection\MediaAlbumTranslationDetailCollection;
use Shopware\Api\Media\Event\MediaAlbum\MediaAlbumBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MediaAlbumTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaAlbumTranslationDetailCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationDetailCollection $mediaAlbumTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationDetailCollection
    {
        return $this->mediaAlbumTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->mediaAlbumTranslations->getMediaAlbum()->count() > 0) {
            $events[] = new MediaAlbumBasicLoadedEvent($this->mediaAlbumTranslations->getMediaAlbum(), $this->context);
        }
        if ($this->mediaAlbumTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->mediaAlbumTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
