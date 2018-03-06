<?php

namespace Shopware\Api\Product\Event\ProductConfigurator;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Product\Struct\ProductConfiguratorSearchResult;

class ProductConfiguratorSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.search.result.loaded';

    /**
     * @var ProductConfiguratorSearchResult
     */
    protected $result;

    public function __construct(ProductConfiguratorSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->result->getContext();
    }
}