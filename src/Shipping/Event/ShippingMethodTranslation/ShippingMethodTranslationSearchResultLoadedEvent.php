<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethodTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shipping\Struct\ShippingMethodTranslationSearchResult;

class ShippingMethodTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method_translation.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
