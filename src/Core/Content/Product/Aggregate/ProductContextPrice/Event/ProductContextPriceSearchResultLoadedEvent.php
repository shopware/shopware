<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductContextPriceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.search.result.loaded';

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
