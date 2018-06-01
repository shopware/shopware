<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransaction\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionSearchResult;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionSearchResult
     */
    protected $result;

    public function __construct(OrderTransactionSearchResult $result)
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
