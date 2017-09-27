<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;

class ProductDetailPriceBasicLoadedEvent extends NestedEvent
{
    const NAME = 'productDetailPrice.basic.loaded';

    /**
     * @var ProductDetailPriceBasicCollection
     */
    protected $productDetailPrices;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductDetailPriceBasicCollection $productDetailPrices, TranslationContext $context)
    {
        $this->productDetailPrices = $productDetailPrices;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductDetailPrices(): ProductDetailPriceBasicCollection
    {
        return $this->productDetailPrices;
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
