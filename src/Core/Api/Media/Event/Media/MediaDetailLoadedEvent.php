<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\Media;

use Shopware\Api\Media\Collection\MediaDetailCollection;
use Shopware\Api\Media\Event\MediaAlbum\MediaAlbumBasicLoadedEvent;
use Shopware\Api\User\Event\User\UserBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MediaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaDetailCollection
     */
    protected $media;

    public function __construct(MediaDetailCollection $media, ApplicationContext $context)
    {
        $this->context = $context;
        $this->media = $media;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
