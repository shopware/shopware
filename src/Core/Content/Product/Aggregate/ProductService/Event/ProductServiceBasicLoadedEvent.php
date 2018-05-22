<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Event\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\System\Tax\Event\TaxBasicLoadedEvent;

class ProductServiceBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_service.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection
     */
    protected $productServices;

    public function __construct(ProductServiceBasicCollection $productServices, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productServices = $productServices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getProductServices(): ProductServiceBasicCollection
    {
        return $this->productServices;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productServices->getOptions()->count() > 0) {
            $events[] = new ConfigurationGroupOptionBasicLoadedEvent($this->productServices->getOptions(), $this->context);
        }
        if ($this->productServices->getTaxes()->count() > 0) {
            $events[] = new TaxBasicLoadedEvent($this->productServices->getTaxes(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
