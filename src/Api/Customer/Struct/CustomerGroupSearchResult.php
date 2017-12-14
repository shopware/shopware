<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Struct;

use Shopware\Api\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CustomerGroupSearchResult extends CustomerGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
