<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductListingPrice;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductListingPriceSearchResult;

class ProductListingPriceSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_listing_price.search.result.loaded';

    /**
     * @var ProductListingPriceSearchResult
     */
    protected $result;

    public function __construct(ProductListingPriceSearchResult $result)
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
