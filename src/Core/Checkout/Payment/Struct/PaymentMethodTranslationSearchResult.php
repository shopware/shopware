<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Checkout\Payment\Collection\PaymentMethodTranslationBasicCollection;

class PaymentMethodTranslationSearchResult extends PaymentMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
