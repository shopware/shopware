<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductSearchKeyword;

use Shopware\Api\Product\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ProductSearchKeywordBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_search_keyword.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ProductSearchKeywordBasicCollection
     */
    protected $productSearchKeywords;

    public function __construct(ProductSearchKeywordBasicCollection $productSearchKeywords, ShopContext $context)
    {
        $this->context = $context;
        $this->productSearchKeywords = $productSearchKeywords;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getProductSearchKeywords(): ProductSearchKeywordBasicCollection
    {
        return $this->productSearchKeywords;
    }
}
