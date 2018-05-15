<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Collection\CustomerBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CustomerSearchResult extends CustomerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
