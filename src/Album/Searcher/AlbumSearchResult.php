<?php declare(strict_types=1);

namespace Shopware\Album\Searcher;

use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class AlbumSearchResult extends AlbumBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
