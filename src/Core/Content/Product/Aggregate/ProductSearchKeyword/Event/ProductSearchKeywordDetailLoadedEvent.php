<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Collection\ProductSearchKeywordDetailCollection;
use Shopware\Core\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ProductSearchKeywordDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_search_keyword.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductSearchKeywordDetailCollection
     */
    protected $productSearchKeywords;

    public function __construct(ProductSearchKeywordDetailCollection $productSearchKeywords, Context $context)
    {
        $this->context = $context;
        $this->productSearchKeywords = $productSearchKeywords;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->productSearchKeywords->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->productSearchKeywords->getLanguages(), $this->context);
        }
        if ($this->productSearchKeywords->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productSearchKeywords->getProducts(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
