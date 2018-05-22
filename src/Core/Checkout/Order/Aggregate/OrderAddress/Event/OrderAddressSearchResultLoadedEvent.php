<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderAddress\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressSearchResult;
use Shopware\Framework\Event\NestedEvent;

class OrderAddressSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_address.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
