<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductSearchKeyword;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductSearchKeywordSearchResult;

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
