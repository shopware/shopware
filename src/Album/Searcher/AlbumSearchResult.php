<?php declare(strict_types=1);

namespace Shopware\Album\Searcher;

use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;

class AlbumSearchResult extends AlbumBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
