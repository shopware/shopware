<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Struct\ProductSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product.search.result.loaded';

    /**
     * @var ProductSearchResult
     */
    protected $result;

    public function __construct(ProductSearchResult $result)
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
