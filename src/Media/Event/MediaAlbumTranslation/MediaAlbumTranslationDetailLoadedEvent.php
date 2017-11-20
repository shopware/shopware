<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaAlbumTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Media\Collection\MediaAlbumTranslationDetailCollection;
use Shopware\Media\Event\MediaAlbum\MediaAlbumBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class MediaAlbumTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'media_album_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MediaAlbumTranslationDetailCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationDetailCollection $mediaAlbumTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
            $events[] = new ShopBasicLoadedEvent($this->mediaAlbumTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
