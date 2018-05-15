<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductManufacturer;

use Shopware\Content\Product\Struct\ProductManufacturerSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
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
