<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Struct\ShippingMethodTranslationSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ShippingMethodTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_translation.search.result.loaded';

    /**
     * @var ShippingMethodTranslationSearchResult
     */
    protected $result;

    public function __construct(ShippingMethodTranslationSearchResult $result)
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
