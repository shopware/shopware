<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductSearchKeyword\Event;

use Shopware\Framework\Context;
use Shopware\Content\Product\Aggregate\ProductSearchKeyword\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class ProductSearchKeywordBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_search_keyword.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductSearchKeywordBasicCollection
     */
    protected $productSearchKeywords;

    public function __construct(ProductSearchKeywordBasicCollection $productSearchKeywords, Context $context)
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

    public function getProductSearchKeywords(): ProductSearchKeywordBasicCollection
    {
        return $this->productSearchKeywords;
    }
}
