<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderLineItem;

use Shopware\Api\Order\Struct\OrderLineItemSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
