<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductDetailPrice\Event\ProductDetailPriceBasicLoadedEvent;
use Shopware\Unit\Event\UnitBasicLoadedEvent;

class ProductDetailBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product_detail.basic.loaded';

    /**
     * @var ProductDetailBasicCollection
     */
    protected $productDetails;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductDetailBasicCollection $productDetails, TranslationContext $context)
    {
        $this->productDetails = $productDetails;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductDetails(): ProductDetailBasicCollection
    {
        return $this->productDetails;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productDetails->getUnits()->count() > 0) {
            $events[] = new UnitBasicLoadedEvent($this->productDetails->getUnits(), $this->context);
        }
        if ($this->productDetails->getPrices()->count() > 0) {
            $events[] = new ProductDetailPriceBasicLoadedEvent($this->productDetails->getPrices(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
