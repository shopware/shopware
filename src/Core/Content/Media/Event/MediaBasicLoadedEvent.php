<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Event\MediaAlbumBasicLoadedEvent;
use Shopware\Core\Content\Media\Collection\MediaBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class MediaBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var MediaBasicCollection
     */
    protected $media;

    public function __construct(MediaBasicCollection $media, Context $context)
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

    public function getMedia(): MediaBasicCollection
    {
        return $this->media;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->media->getAlbums()->count() > 0) {
            $events[] = new MediaAlbumBasicLoadedEvent($this->media->getAlbums(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
