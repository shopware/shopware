<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductConfigurator\Event;

use Shopware\Content\Product\Aggregate\ProductConfigurator\Struct\ProductConfiguratorSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductConfiguratorSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.search.result.loaded';

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductConfigurator\Struct\ProductConfiguratorSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
