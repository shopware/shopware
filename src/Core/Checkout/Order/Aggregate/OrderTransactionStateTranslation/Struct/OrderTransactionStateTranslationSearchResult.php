<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Collection\OrderTransactionStateTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderTransactionStateTranslationSearchResult extends OrderTransactionStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
