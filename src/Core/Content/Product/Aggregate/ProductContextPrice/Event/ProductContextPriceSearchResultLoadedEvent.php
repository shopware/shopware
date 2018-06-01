<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductContextPriceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceSearchResult
     */
    protected $result;

    public function __construct(ProductContextPriceSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
