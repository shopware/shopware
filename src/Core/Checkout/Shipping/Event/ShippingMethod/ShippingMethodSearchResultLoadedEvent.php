<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event\ShippingMethod;

use Shopware\Checkout\Shipping\Struct\ShippingMethodSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShippingMethodSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method.search.result.loaded';

    /**
     * @var ShippingMethodSearchResult
     */
    protected $result;

    public function __construct(ShippingMethodSearchResult $result)
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
