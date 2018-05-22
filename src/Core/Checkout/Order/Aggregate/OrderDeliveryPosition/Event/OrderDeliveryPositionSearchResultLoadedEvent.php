<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionSearchResult;
use Shopware\Framework\Event\NestedEvent;

class OrderDeliveryPositionSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery_position.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionSearchResult
     */
    protected $result;

    public function __construct(OrderDeliveryPositionSearchResult $result)
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
