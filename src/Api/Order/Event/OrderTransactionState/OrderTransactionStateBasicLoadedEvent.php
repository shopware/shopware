<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionState;

use Shopware\Api\Order\Collection\OrderTransactionStateBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var OrderTransactionStateBasicCollection
     */
    protected $orderTransactionStates;

    public function __construct(OrderTransactionStateBasicCollection $orderTransactionStates, ShopContext $context)
    {
        $this->context = $context;
        $this->orderTransactionStates = $orderTransactionStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getOrderTransactionStates(): OrderTransactionStateBasicCollection
    {
        return $this->orderTransactionStates;
    }
}
