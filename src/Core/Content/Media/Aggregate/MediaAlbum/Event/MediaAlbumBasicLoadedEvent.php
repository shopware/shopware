<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbum\Event;

use Shopware\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MediaAlbumBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaAlbumBasicCollection
     */
    protected $mediaAlbum;

    public function __construct(MediaAlbumBasicCollection $mediaAlbum, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMediaAlbum(): MediaAlbumBasicCollection
    {
        return $this->mediaAlbum;
    }
}
