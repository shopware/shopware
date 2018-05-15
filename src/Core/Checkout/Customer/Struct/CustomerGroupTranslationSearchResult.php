<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CustomerGroupTranslationSearchResult extends CustomerGroupTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
