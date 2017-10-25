<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;

class ProductListingPriceBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product_listing_price_ro.basic.loaded';

    /**
     * @var ProductListingPriceBasicCollection
     */
    protected $productListingPrices;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductListingPriceBasicCollection $productListingPrices, TranslationContext $context)
    {
        $this->productListingPrices = $productListingPrices;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductListingPrices(): ProductListingPriceBasicCollection
    {
        return $this->productListingPrices;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
