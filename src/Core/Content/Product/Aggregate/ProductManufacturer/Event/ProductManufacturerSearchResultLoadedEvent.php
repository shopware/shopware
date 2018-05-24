<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerSearchResult;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.search.result.loaded';

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
