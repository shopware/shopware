<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CustomerGroupTranslationSearchResult extends CustomerGroupTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
