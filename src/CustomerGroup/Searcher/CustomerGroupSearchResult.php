<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;

class CustomerGroupSearchResult extends CustomerGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
