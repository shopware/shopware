<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Event\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\Core\System\Tax\Event\TaxBasicLoadedEvent;

class ProductServiceBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_service.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection
     */
    protected $productServices;

    public function __construct(ProductServiceBasicCollection $productServices, Context $context)
    {
        $this->context = $context;
        $this->productServices = $productServices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
