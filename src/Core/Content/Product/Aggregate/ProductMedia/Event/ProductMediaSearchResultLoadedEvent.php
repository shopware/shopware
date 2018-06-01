<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductMedia\Event;

use Shopware\Framework\Context;
use Shopware\Content\Product\Aggregate\ProductMedia\Struct\ProductMediaSearchResult;
use Shopware\Framework\Event\NestedEvent;

class ProductMediaSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_media.search.result.loaded';

    /**
     * @var ProductMediaSearchResult
     */
    protected $result;

    public function __construct(ProductMediaSearchResult $result)
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
