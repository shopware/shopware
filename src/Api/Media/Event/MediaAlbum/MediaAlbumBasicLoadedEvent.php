<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbum;

use Shopware\Api\Media\Collection\MediaAlbumBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class MediaAlbumBasicLoadedEvent extends NestedEvent
{
    const NAME = 'media_album.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MediaAlbumBasicCollection
     */
    protected $mediaAlbum;

    public function __construct(MediaAlbumBasicCollection $mediaAlbum, TranslationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getMediaAlbum(): MediaAlbumBasicCollection
    {
        return $this->mediaAlbum;
    }
}
