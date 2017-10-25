<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Searcher;

use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
