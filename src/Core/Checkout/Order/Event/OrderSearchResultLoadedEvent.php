<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\Struct\OrderSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

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
