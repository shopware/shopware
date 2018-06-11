<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class MediaAlbumBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var MediaAlbumBasicCollection
     */
    protected $mediaAlbum;

    public function __construct(MediaAlbumBasicCollection $mediaAlbum, Context $context)
    {
        $this->context = $context;
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMediaAlbum(): MediaAlbumBasicCollection
    {
        return $this->mediaAlbum;
    }
}
