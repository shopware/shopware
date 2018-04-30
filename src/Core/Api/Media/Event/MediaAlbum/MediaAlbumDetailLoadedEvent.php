<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbum;

use Shopware\Api\Media\Collection\MediaAlbumDetailCollection;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Api\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MediaAlbumDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaAlbumDetailCollection
     */
    protected $mediaAlbum;

    public function __construct(MediaAlbumDetailCollection $mediaAlbum, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMediaAlbum(): MediaAlbumDetailCollection
    {
        return $this->mediaAlbum;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->mediaAlbum->getParents()->count() > 0) {
            $events[] = new MediaAlbumBasicLoadedEvent($this->mediaAlbum->getParents(), $this->context);
        }
        if ($this->mediaAlbum->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->mediaAlbum->getMedia(), $this->context);
        }
        if ($this->mediaAlbum->getChildren()->count() > 0) {
            $events[] = new MediaAlbumBasicLoadedEvent($this->mediaAlbum->getChildren(), $this->context);
        }
        if ($this->mediaAlbum->getTranslations()->count() > 0) {
            $events[] = new MediaAlbumTranslationBasicLoadedEvent($this->mediaAlbum->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
