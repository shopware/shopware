<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum\Event;

use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class MediaAlbumSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album.search.result.loaded';

    /**
     * @var MediaAlbumSearchResult
     */
    protected $result;

    public function __construct(MediaAlbumSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
