<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Event\MediaAlbumBasicLoadedEvent;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationDetailCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class MediaAlbumTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationDetailCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationDetailCollection $mediaAlbumTranslations, Context $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
