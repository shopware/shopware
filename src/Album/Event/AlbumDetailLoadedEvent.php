<?php declare(strict_types=1);

namespace Shopware\Album\Event;

use Shopware\Album\Struct\AlbumDetailCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Media\Event\MediaBasicLoadedEvent;

class AlbumDetailLoadedEvent extends NestedEvent
{
    const NAME = 'album.detail.loaded';

    /**
     * @var AlbumDetailCollection
     */
    protected $albums;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AlbumDetailCollection $albums, TranslationContext $context)
    {
        $this->albums = $albums;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAlbums(): AlbumDetailCollection
    {
        return $this->albums;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new AlbumBasicLoadedEvent($this->albums, $this->context),
            new MediaBasicLoadedEvent($this->albums->getMedias(), $this->context),
        ]);
    }
}
