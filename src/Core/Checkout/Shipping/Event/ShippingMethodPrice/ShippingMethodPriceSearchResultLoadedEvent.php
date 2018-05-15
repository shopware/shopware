<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event\ShippingMethodPrice;

use Shopware\Checkout\Shipping\Struct\ShippingMethodPriceSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShippingMethodPriceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_price.search.result.loaded';

    /**
     * @var ShippingMethodPriceSearchResult
     */
    protected $result;

    public function __construct(ShippingMethodPriceSearchResult $result)
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
