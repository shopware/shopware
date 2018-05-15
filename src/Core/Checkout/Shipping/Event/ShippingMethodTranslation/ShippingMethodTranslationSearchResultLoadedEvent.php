<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event\ShippingMethodTranslation;

use Shopware\Checkout\Shipping\Struct\ShippingMethodTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
