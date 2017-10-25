<?php declare(strict_types=1);

namespace Shopware\Media\Searcher;

use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class MediaSearchResult extends MediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
