<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService\Event;

use Shopware\Content\Product\Aggregate\ProductService\Struct\ProductServiceSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductServiceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_service.search.result.loaded';

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductService\Struct\ProductServiceSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
