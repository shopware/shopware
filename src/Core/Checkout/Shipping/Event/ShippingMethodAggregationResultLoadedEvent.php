<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\AggregatorResult;

class ShippingMethodAggregationResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method.aggregation.result.loaded';

    /**
     * @var AggregatorResult
     */
    protected $result;

    public function __construct(AggregatorResult $result)
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

    public function getResult(): AggregatorResult
    {
        return $this->result;
    }
}
