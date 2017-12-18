<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductPrice;

use Shopware\Api\Product\Struct\ProductPriceSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductPriceSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_price.search.result.loaded';

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
