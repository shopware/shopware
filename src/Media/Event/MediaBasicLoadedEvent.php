<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Album\Event\AlbumBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Media\Struct\MediaBasicCollection;

class MediaBasicLoadedEvent extends NestedEvent
{
    const NAME = 'media.basic.loaded';

    /**
     * @var MediaBasicCollection
     */
    protected $media;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(MediaBasicCollection $media, TranslationContext $context)
    {
        $this->media = $media;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getMedia(): MediaBasicCollection
    {
        return $this->media;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->media->getAlbum()->count() > 0) {
            $events[] = new AlbumBasicLoadedEvent($this->media->getAlbum(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
