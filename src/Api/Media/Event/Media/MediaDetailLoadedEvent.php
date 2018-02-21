<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\Media;

use Shopware\Api\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\Api\Mail\Event\MailAttachment\MailAttachmentBasicLoadedEvent;
use Shopware\Api\Media\Collection\MediaDetailCollection;
use Shopware\Api\Media\Event\MediaAlbum\MediaAlbumBasicLoadedEvent;
use Shopware\Api\Media\Event\MediaTranslation\MediaTranslationBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductMedia\ProductMediaBasicLoadedEvent;
use Shopware\Api\User\Event\User\UserBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MediaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var MediaDetailCollection
     */
    protected $media;

    public function __construct(MediaDetailCollection $media, ShopContext $context)
    {
        $this->context = $context;
        $this->media = $media;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
        if ($this->media->getProductManufacturers()->count() > 0) {
            $events[] = new ProductManufacturerBasicLoadedEvent($this->media->getProductManufacturers(), $this->context);
        }
        if ($this->media->getProductMedia()->count() > 0) {
            $events[] = new ProductMediaBasicLoadedEvent($this->media->getProductMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
