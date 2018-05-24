<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
