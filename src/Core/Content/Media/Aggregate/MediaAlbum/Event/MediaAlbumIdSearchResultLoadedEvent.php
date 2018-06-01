<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbum\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\IdSearchResult;

class MediaAlbumIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'media_album.id.search.result.loaded';

    /**
     * @var IdSearchResult
     */
    protected $result;

    public function __construct(IdSearchResult $result)
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

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
