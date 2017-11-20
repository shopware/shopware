<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaAlbumTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Media\Struct\MediaAlbumTranslationSearchResult;

class MediaAlbumTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'media_album_translation.search.result.loaded';

    /**
     * @var MediaAlbumTranslationSearchResult
     */
    protected $result;

    public function __construct(MediaAlbumTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
