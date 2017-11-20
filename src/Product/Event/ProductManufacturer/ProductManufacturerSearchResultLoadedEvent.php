<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductManufacturer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Struct\ProductManufacturerSearchResult;

class ProductManufacturerSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product_manufacturer.search.result.loaded';

    /**
     * @var ProductManufacturerSearchResult
     */
    protected $result;

    public function __construct(ProductManufacturerSearchResult $result)
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
