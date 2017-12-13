<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Customer\Collection\CustomerGroupBasicCollection;

class CustomerGroupSearchResult extends CustomerGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
