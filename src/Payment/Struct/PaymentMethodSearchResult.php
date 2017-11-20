<?php declare(strict_types=1);

namespace Shopware\Payment\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Payment\Collection\PaymentMethodBasicCollection;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
