<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderAddressSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_address.search.result.loaded';

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressSearchResult
     */
    protected $result;

    public function __construct(OrderAddressSearchResult $result)
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
