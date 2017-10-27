<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;

class ShopTemplateSearchResult extends ShopTemplateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
