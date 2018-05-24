<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductSearchKeyword\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\Aggregate\ProductSearchKeyword\Struct\ProductSearchKeywordSearchResult;
use Shopware\Framework\Event\NestedEvent;

class ProductSearchKeywordSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_search_keyword.search.result.loaded';

    /**
     * @var ProductSearchKeywordSearchResult
     */
    protected $result;

    public function __construct(ProductSearchKeywordSearchResult $result)
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
