<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection\OrderStateBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderStateBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var OrderStateBasicCollection
     */
    protected $orderStates;

    public function __construct(OrderStateBasicCollection $orderStates, Context $context)
    {
        $this->context = $context;
        $this->orderStates = $orderStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderStates(): OrderStateBasicCollection
    {
        return $this->orderStates;
    }
}
