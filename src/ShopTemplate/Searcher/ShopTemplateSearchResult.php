<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;

class ShopTemplateSearchResult extends ShopTemplateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
