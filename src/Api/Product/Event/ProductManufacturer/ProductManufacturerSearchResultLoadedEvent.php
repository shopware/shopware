<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductManufacturer;

use Shopware\Api\Product\Struct\ProductManufacturerSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
