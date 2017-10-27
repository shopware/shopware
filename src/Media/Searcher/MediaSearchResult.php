<?php declare(strict_types=1);

namespace Shopware\Media\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Media\Struct\MediaBasicCollection;

class MediaSearchResult extends MediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
