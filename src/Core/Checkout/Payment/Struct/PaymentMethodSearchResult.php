<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
