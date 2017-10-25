<?php declare(strict_types=1);

namespace Shopware\Currency\Searcher;

use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
