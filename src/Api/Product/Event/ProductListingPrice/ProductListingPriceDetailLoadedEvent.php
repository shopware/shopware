<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductListingPrice;

use Shopware\Api\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Api\Product\Collection\ProductListingPriceDetailCollection;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductListingPriceDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_listing_price.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductListingPriceDetailCollection
     */
    protected $productListingPrices;

    public function __construct(ProductListingPriceDetailCollection $productListingPrices, TranslationContext $context)
    {
        $this->context = $context;
        $this->productListingPrices = $productListingPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getProductListingPrices(): ProductListingPriceDetailCollection
    {
        return $this->productListingPrices;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productListingPrices->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productListingPrices->getProducts(), $this->context);
        }
        if ($this->productListingPrices->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->productListingPrices->getCustomerGroups(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
