<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderLineItemSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_line_item.search.result.loaded';

    /**
     * @var OrderLineItemSearchResult
     */
    protected $result;

    public function __construct(OrderLineItemSearchResult $result)
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
