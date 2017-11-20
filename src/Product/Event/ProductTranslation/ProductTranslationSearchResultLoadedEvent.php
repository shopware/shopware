<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductTranslationSearchResult;

class ProductTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_translation.search.result.loaded';

    /**
     * @var ProductTranslationSearchResult
     */
    protected $result;

    public function __construct(ProductTranslationSearchResult $result)
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
