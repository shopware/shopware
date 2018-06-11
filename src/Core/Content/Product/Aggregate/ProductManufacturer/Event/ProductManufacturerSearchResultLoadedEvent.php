<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductManufacturerSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
