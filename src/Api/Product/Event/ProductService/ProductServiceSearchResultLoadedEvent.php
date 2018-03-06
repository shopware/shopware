<?php

namespace Shopware\Api\Product\Event\ProductService;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Product\Struct\ProductServiceSearchResult;

class ProductServiceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_service.search.result.loaded';

    /**
     * @var ProductServiceSearchResult
     */
    protected $result;

    public function __construct(ProductServiceSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->result->getContext();
    }
}