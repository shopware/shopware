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
    protected $medias;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(MediaBasicCollection $medias, TranslationContext $context)
    {
        $this->medias = $medias;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getMedias(): MediaBasicCollection
    {
        return $this->medias;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new AlbumBasicLoadedEvent($this->medias->getAlbums(), $this->context),
        ]);
    }
}
