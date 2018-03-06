<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductConfigurator;

use Shopware\Api\Configuration\Event\ConfigurationGroupOption\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\Api\Product\Collection\ProductConfiguratorBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductConfiguratorBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ProductConfiguratorBasicCollection
     */
    protected $productConfigurators;

    public function __construct(ProductConfiguratorBasicCollection $productConfigurators, ShopContext $context)
    {
        $this->context = $context;
        $this->productConfigurators = $productConfigurators;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getProductConfigurators(): ProductConfiguratorBasicCollection
    {
        return $this->productConfigurators;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productConfigurators->getConfigurationOptions()->count() > 0) {
            $events[] = new ConfigurationGroupOptionBasicLoadedEvent($this->productConfigurators->getConfigurationOptions(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
