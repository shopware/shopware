<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaAlbumTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Media\Collection\MediaAlbumTranslationBasicCollection;

class MediaAlbumTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'media_album_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MediaAlbumTranslationBasicCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationBasicCollection $mediaAlbumTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationBasicCollection
    {
        return $this->mediaAlbumTranslations;
    }
}
