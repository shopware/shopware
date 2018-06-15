<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Event;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Struct\ProductConfiguratorSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductConfiguratorSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Struct\ProductConfiguratorSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
