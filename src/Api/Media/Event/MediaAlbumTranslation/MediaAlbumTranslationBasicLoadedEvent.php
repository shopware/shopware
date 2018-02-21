<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbumTranslation;

use Shopware\Api\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class MediaAlbumTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var MediaAlbumTranslationBasicCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationBasicCollection $mediaAlbumTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationBasicCollection
    {
        return $this->mediaAlbumTranslations;
    }
}
