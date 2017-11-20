<?php declare(strict_types=1);

namespace Shopware\Media\Event\Media;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Media\Collection\MediaBasicCollection;
use Shopware\Media\Event\MediaAlbum\MediaAlbumBasicLoadedEvent;

class MediaBasicLoadedEvent extends NestedEvent
{
    const NAME = 'media.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MediaBasicCollection
     */
    protected $media;

    public function __construct(MediaBasicCollection $media, TranslationContext $context)
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
