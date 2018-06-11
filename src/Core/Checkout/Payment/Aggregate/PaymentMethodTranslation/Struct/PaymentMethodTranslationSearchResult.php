<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Struct;

use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class PaymentMethodTranslationSearchResult extends PaymentMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
