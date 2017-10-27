<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
