<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Struct;

use Shopware\Core\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
