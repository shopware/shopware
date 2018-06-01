<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Event\MediaAlbumBasicLoadedEvent;
use Shopware\Core\Content\Media\Collection\MediaDetailCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\User\Event\UserBasicLoadedEvent;

class MediaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MediaDetailCollection
     */
    protected $media;

    public function __construct(MediaDetailCollection $media, Context $context)
    {
        $this->context = $context;
        $this->media = $media;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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

        return new NestedEventCollection($events);
    }
}
