<?php declare(strict_types=1);

namespace Shopware\Tax\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\Tax\Struct\TaxBasicCollection;

class TaxSearchResult extends TaxBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
