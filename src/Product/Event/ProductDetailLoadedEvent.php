<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\ProductDetail\Event\ProductDetailBasicLoadedEvent;
use Shopware\ProductVote\Event\ProductVoteBasicLoadedEvent;

class ProductDetailLoadedEvent extends NestedEvent
{
    const NAME = 'product.detail.loaded';

    /**
     * @var ProductDetailCollection
     */
    protected $products;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductDetailCollection $products, TranslationContext $context)
    {
        $this->products = $products;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProducts(): ProductDetailCollection
    {
        return $this->products;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new ProductBasicLoadedEvent($this->products, $this->context),
        ];

        if ($this->products->getDetails()->count() > 0) {
            $events[] = new ProductDetailBasicLoadedEvent($this->products->getDetails(), $this->context);
        }
        if ($this->products->getCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->products->getCategories(), $this->context);
        }
        if ($this->products->getCategoryTree()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->products->getCategoryTree(), $this->context);
        }
        if ($this->products->getVotes()->count() > 0) {
            $events[] = new ProductVoteBasicLoadedEvent($this->products->getVotes(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
