<?php declare(strict_types=1);

namespace Shopware\Payment\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Payment\Collection\PaymentMethodTranslationBasicCollection;

class PaymentMethodTranslationSearchResult extends PaymentMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
