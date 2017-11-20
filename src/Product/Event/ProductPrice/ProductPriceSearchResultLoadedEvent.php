<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductPrice;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductPriceSearchResult;

class ProductPriceSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_price.search.result.loaded';

    /**
     * @var ProductPriceSearchResult
     */
    protected $result;

    public function __construct(ProductPriceSearchResult $result)
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
