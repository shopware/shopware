<?php

namespace Shopware\Api\Product\Event\ProductContextPrice;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Product\Collection\ProductContextPriceBasicCollection;


class ProductContextPriceBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ProductContextPriceBasicCollection
     */
    protected $productContextPrices;

    public function __construct(ProductContextPriceBasicCollection $productContextPrices, ShopContext $context)
    {
        $this->context = $context;
        $this->productContextPrices = $productContextPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getProductContextPrices(): ProductContextPriceBasicCollection
    {
        return $this->productContextPrices;
    }

}