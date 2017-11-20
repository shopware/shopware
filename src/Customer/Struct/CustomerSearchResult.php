<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Customer\Collection\CustomerBasicCollection;

class CustomerSearchResult extends CustomerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
