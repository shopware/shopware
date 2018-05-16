<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionState\Event;

use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
