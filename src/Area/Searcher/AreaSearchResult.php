<?php declare(strict_types=1);

namespace Shopware\Area\Searcher;

use Shopware\Area\Struct\AreaBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class AreaSearchResult extends AreaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
