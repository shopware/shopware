<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductConfigurator;

use Shopware\System\Configuration\Event\ConfigurationGroupOption\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\Content\Product\Collection\ProductConfiguratorBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductConfiguratorBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_configurator.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductConfiguratorBasicCollection
     */
    protected $productConfigurators;

    public function __construct(ProductConfiguratorBasicCollection $productConfigurators, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productConfigurators = $productConfigurators;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
