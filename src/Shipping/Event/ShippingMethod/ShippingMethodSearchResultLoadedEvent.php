<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethod;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shipping\Struct\ShippingMethodSearchResult;

class ShippingMethodSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
