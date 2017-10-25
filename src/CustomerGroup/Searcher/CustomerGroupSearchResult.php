<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Searcher;

use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CustomerGroupSearchResult extends CustomerGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
