<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderDeliveryPosition;

use Shopware\Api\Order\Struct\OrderDeliveryPositionSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderDeliveryPositionSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery_position.search.result.loaded';

    /**
     * @var OrderDeliveryPositionSearchResult
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
