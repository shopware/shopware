<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Struct;

use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class PaymentMethodTranslationSearchResult extends PaymentMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
