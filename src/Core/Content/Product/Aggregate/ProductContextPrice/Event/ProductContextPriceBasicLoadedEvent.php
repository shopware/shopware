<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductContextPriceBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ProductContextPriceBasicCollection
     */
    protected $productContextPrices;

    public function __construct(ProductContextPriceBasicCollection $productContextPrices, Context $context)
    {
        $this->context = $context;
        $this->productContextPrices = $productContextPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductContextPrices(): ProductContextPriceBasicCollection
    {
        return $this->productContextPrices;
    }
}
