<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductConfigurator\Event;

use Shopware\Framework\Context;
use Shopware\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Event\ConfigurationGroupOptionBasicLoadedEvent;

class ProductConfiguratorBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection
     */
    protected $productConfigurators;

    public function __construct(ProductConfiguratorBasicCollection $productConfigurators, Context $context)
    {
        $this->context = $context;
        $this->productConfigurators = $productConfigurators;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->productConfigurators->getOptions()->count() > 0) {
            $events[] = new ConfigurationGroupOptionBasicLoadedEvent($this->productConfigurators->getOptions(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
