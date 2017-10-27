<?php declare(strict_types=1);

namespace Shopware\Area\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Area\Struct\AreaBasicCollection;

class AreaSearchResult extends AreaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
