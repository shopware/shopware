<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct\ProductPriceRuleSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductPriceRuleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_price_rule.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct\ProductPriceRuleSearchResult
     */
    protected $result;

    public function __construct(ProductPriceRuleSearchResult $result)
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
