<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CustomerGroupTranslationSearchResult extends CustomerGroupTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
