<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductDetail\Struct\ProductDetailDetailCollection;
use Shopware\ProductPrice\Event\ProductPriceBasicLoadedEvent;

class ProductDetailDetailLoadedEvent extends NestedEvent
{
    const NAME = 'productDetail.detail.loaded';

    /**
     * @var ProductDetailDetailCollection
     */
    protected $productDetails;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductDetailDetailCollection $productDetails, TranslationContext $context)
    {
        $this->productDetails = $productDetails;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductDetails(): ProductDetailDetailCollection
    {
        return $this->productDetails;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ProductDetailBasicLoadedEvent($this->productDetails, $this->context),
            new ProductPriceBasicLoadedEvent($this->productDetails->getPrices(), $this->context),
        ]);
    }
}
