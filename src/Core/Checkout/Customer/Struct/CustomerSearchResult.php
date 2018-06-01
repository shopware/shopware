<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Struct;

use Shopware\Core\Checkout\Customer\Collection\CustomerBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CustomerSearchResult extends CustomerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
