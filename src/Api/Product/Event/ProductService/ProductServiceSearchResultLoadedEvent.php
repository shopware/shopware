<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductService;

use Shopware\Api\Product\Struct\ProductServiceSearchResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

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
