<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream\Event;

use Shopware\Core\Content\Product\Aggregate\ProductStream\Collection\ProductStreamDetailCollection;
use Shopware\Core\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Listing\Event\ListingSortingBasicLoadedEvent;

class ProductStreamDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_stream.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductStream\Collection\ProductStreamDetailCollection
     */
    protected $productStreams;

    public function __construct(ProductStreamDetailCollection $productStreams, Context $context)
    {
        $this->context = $context;
        $this->productStreams = $productStreams;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
