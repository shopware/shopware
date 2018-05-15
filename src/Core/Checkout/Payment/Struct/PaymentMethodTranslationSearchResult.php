<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Checkout\Payment\Collection\PaymentMethodTranslationBasicCollection;

class PaymentMethodTranslationSearchResult extends PaymentMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
