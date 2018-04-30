<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderAddress;

use Shopware\Api\Order\Struct\OrderAddressSearchResult;
use Shopware\Context\Struct\ApplicationContext;
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
