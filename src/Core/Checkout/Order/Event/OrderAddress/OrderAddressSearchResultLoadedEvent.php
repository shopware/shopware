<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderAddress;

use Shopware\Checkout\Order\Struct\OrderAddressSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderAddressSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_address.search.result.loaded';

    /**
     * @var OrderAddressSearchResult
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
