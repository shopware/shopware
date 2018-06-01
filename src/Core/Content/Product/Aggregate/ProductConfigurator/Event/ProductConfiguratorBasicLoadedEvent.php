<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupOption\Event\ConfigurationGroupOptionBasicLoadedEvent;

class ProductConfiguratorBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection
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
