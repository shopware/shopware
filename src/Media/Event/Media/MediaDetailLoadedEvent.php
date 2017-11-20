<?php declare(strict_types=1);

namespace Shopware\Media\Event\Media;

use Shopware\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Mail\Event\MailAttachment\MailAttachmentBasicLoadedEvent;
use Shopware\Media\Collection\MediaDetailCollection;
use Shopware\Media\Event\MediaAlbum\MediaAlbumBasicLoadedEvent;
use Shopware\Media\Event\MediaTranslation\MediaTranslationBasicLoadedEvent;
use Shopware\Product\Event\ProductMedia\ProductMediaBasicLoadedEvent;
use Shopware\User\Event\User\UserBasicLoadedEvent;

class MediaDetailLoadedEvent extends NestedEvent
{
    const NAME = 'media.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MediaDetailCollection
     */
    protected $media;

    public function __construct(MediaDetailCollection $media, TranslationContext $context)
    {
        $this->context = $context;
        $this->media = $media;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getMedia(): MediaDetailCollection
    {
        return $this->media;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->media->getAlbums()->count() > 0) {
            $events[] = new MediaAlbumBasicLoadedEvent($this->media->getAlbums(), $this->context);
        }
        if ($this->media->getUsers()->count() > 0) {
            $events[] = new UserBasicLoadedEvent($this->media->getUsers(), $this->context);
        }
        if ($this->media->getCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->media->getCategories(), $this->context);
        }
        if ($this->media->getMailAttachments()->count() > 0) {
            $events[] = new MailAttachmentBasicLoadedEvent($this->media->getMailAttachments(), $this->context);
        }
        if ($this->media->getTranslations()->count() > 0) {
            $events[] = new MediaTranslationBasicLoadedEvent($this->media->getTranslations(), $this->context);
        }
        if ($this->media->getProductMedia()->count() > 0) {
            $events[] = new ProductMediaBasicLoadedEvent($this->media->getProductMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
