<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderDeliveryPosition;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Order\Struct\OrderDeliveryPositionSearchResult;

class OrderDeliveryPositionSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'order_delivery_position.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
