<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductMedia;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductMediaSearchResult;

class ProductMediaSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_media.search.result.loaded';

    /**
     * @var ProductMediaSearchResult
     */
    protected $result;

    public function __construct(ProductMediaSearchResult $result)
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
