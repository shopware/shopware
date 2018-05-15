<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Checkout\Order\Collection\OrderStateTranslationBasicCollection;

class OrderStateTranslationSearchResult extends OrderStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
