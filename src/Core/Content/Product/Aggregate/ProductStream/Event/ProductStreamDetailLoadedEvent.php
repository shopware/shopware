<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductStream\Event;

use Shopware\System\Listing\Event\ListingSortingBasicLoadedEvent;
use Shopware\Content\Product\Aggregate\ProductStream\Collection\ProductStreamDetailCollection;
use Shopware\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductStreamDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_stream.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductStream\Collection\ProductStreamDetailCollection
     */
    protected $productStreams;

    public function __construct(ProductStreamDetailCollection $productStreams, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productStreams = $productStreams;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getProductStreams(): ProductStreamDetailCollection
    {
        return $this->productStreams;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productStreams->getListingSortings()->count() > 0) {
            $events[] = new ListingSortingBasicLoadedEvent($this->productStreams->getListingSortings(), $this->context);
        }
        if ($this->productStreams->getAllProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productStreams->getAllProducts(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
