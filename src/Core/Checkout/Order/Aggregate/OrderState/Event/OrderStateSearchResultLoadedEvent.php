<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state.search.result.loaded';

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateSearchResult
     */
    protected $result;

    public function __construct(OrderStateSearchResult $result)
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
