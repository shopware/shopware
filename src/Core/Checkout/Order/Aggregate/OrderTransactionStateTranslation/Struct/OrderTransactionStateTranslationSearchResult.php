<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct;

use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Collection\OrderTransactionStateTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderTransactionStateTranslationSearchResult extends OrderTransactionStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
