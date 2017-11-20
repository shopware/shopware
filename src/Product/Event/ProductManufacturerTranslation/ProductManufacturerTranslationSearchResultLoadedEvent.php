<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductManufacturerTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductManufacturerTranslationSearchResult;

class ProductManufacturerTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_manufacturer_translation.search.result.loaded';

    /**
     * @var ProductManufacturerTranslationSearchResult
     */
    protected $result;

    public function __construct(ProductManufacturerTranslationSearchResult $result)
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
