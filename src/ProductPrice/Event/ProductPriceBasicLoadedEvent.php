<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;

class ProductPriceBasicLoadedEvent extends NestedEvent
{
    const NAME = 'productPrice.basic.loaded';

    /**
     * @var ProductPriceBasicCollection
     */
    protected $productPrices;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductPriceBasicCollection $productPrices, TranslationContext $context)
    {
        $this->productPrices = $productPrices;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductPrices(): ProductPriceBasicCollection
    {
        return $this->productPrices;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
