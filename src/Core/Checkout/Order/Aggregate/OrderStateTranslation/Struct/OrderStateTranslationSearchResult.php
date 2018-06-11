<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderStateTranslationSearchResult extends OrderStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
