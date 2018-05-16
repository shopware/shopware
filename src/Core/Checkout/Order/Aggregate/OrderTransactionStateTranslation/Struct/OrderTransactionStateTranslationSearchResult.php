<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Collection\OrderTransactionStateTranslationBasicCollection;

class OrderTransactionStateTranslationSearchResult extends OrderTransactionStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
