<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderTransactionStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state.search.result.loaded';

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateSearchResult
     */
    protected $result;

    public function __construct(OrderTransactionStateSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
