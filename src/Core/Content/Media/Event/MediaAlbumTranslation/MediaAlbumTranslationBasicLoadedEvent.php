<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event\MediaAlbumTranslation;

use Shopware\Content\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MediaAlbumTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaAlbumTranslationBasicCollection
     */
    protected $mediaAlbumTranslations;

    public function __construct(MediaAlbumTranslationBasicCollection $mediaAlbumTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationBasicCollection
    {
        return $this->mediaAlbumTranslations;
    }
}
