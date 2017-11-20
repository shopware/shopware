<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductStream;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductStreamSearchResult;

class ProductStreamSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_stream.search.result.loaded';

    /**
     * @var ProductStreamSearchResult
     */
    protected $result;

    public function __construct(ProductStreamSearchResult $result)
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
