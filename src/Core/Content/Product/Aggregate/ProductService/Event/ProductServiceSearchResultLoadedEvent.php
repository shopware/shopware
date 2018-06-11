<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductService\Struct\ProductServiceSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductServiceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_service.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductService\Struct\ProductServiceSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
