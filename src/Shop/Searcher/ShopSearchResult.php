<?php declare(strict_types=1);

namespace Shopware\Shop\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\Shop\Struct\ShopBasicCollection;

class ShopSearchResult extends ShopBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
