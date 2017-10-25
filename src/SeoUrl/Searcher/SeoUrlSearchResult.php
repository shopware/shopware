<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;

class SeoUrlSearchResult extends SeoUrlBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
