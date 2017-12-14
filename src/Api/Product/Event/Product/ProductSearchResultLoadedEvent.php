<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\Product;

use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
