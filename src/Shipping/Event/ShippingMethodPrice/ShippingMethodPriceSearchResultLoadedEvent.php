<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethodPrice;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shipping\Struct\ShippingMethodPriceSearchResult;

class ShippingMethodPriceSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method_price.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
