<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct;

use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderStateTranslationSearchResult extends OrderStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
