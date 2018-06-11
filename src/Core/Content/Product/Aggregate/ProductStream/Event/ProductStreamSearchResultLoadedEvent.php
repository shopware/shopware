<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductStream\Struct\ProductStreamSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductStreamSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_stream.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductStream\Struct\ProductStreamSearchResult
     */
    protected $result;

    public function __construct(ProductStreamSearchResult $result)
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
