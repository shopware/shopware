<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbum\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumBasicCollection;

class MediaAlbumSearchResult extends MediaAlbumBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
