<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbum\Event;

use Shopware\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
