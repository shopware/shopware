<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductSearchKeyword;

use Shopware\Content\Product\Struct\ProductSearchKeywordSearchResult;
use Shopware\Context\Struct\ApplicationContext;
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
