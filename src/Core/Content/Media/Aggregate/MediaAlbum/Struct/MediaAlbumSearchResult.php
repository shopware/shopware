<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum\Struct;

use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class MediaAlbumSearchResult extends MediaAlbumBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
