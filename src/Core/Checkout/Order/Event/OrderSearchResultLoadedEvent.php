<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Struct\OrderSearchResult;
use Shopware\Framework\Event\NestedEvent;

class OrderSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order.search.result.loaded';

    /**
     * @var OrderSearchResult
     */
    protected $result;

    public function __construct(OrderSearchResult $result)
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
