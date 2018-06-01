<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\IdSearchResult;

class OrderDeliveryPositionIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery_position.id.search.result.loaded';

    /**
     * @var IdSearchResult
     */
    protected $result;

    public function __construct(IdSearchResult $result)
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

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
