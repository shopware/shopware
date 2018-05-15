<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\Product;

use Shopware\Content\Product\Struct\ProductSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
