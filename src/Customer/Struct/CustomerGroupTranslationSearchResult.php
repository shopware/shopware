<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;

class CustomerGroupTranslationSearchResult extends CustomerGroupTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
