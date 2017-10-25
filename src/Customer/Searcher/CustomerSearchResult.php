<?php declare(strict_types=1);

namespace Shopware\Customer\Searcher;

use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CustomerSearchResult extends CustomerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
