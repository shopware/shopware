<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductSearchKeyword;

use Shopware\Api\Product\Collection\ProductSearchKeywordDetailCollection;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductSearchKeywordDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_search_keyword.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductSearchKeywordDetailCollection
     */
    protected $productSearchKeywords;

    public function __construct(ProductSearchKeywordDetailCollection $productSearchKeywords, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productSearchKeywords = $productSearchKeywords;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getProductSearchKeywords(): ProductSearchKeywordDetailCollection
    {
        return $this->productSearchKeywords;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productSearchKeywords->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->productSearchKeywords->getShops(), $this->context);
        }
        if ($this->productSearchKeywords->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productSearchKeywords->getProducts(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
