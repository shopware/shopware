<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Event;

use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class MediaAlbumTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album_translation.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var MediaAlbumTranslationBasicCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationBasicCollection $mediaAlbumTranslations, Context $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationBasicCollection
    {
        return $this->mediaAlbumTranslations;
    }
}
