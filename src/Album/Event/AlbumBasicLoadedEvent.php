<?php declare(strict_types=1);

namespace Shopware\Album\Event;

use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AlbumBasicLoadedEvent extends NestedEvent
{
    const NAME = 'album.basic.loaded';

    /**
     * @var AlbumBasicCollection
     */
    protected $album;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AlbumBasicCollection $album, TranslationContext $context)
    {
        $this->album = $album;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAlbum(): AlbumBasicCollection
    {
        return $this->album;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
