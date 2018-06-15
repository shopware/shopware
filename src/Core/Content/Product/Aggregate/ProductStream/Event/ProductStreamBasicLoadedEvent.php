<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream\Event;

use Shopware\Core\Content\Product\Aggregate\ProductStream\Collection\ProductStreamBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Listing\Event\ListingSortingBasicLoadedEvent;

class ProductStreamBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_stream.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductStreamBasicCollection
     */
    protected $productStreams;

    public function __construct(ProductStreamBasicCollection $productStreams, Context $context)
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

    public function getProductStreams(): ProductStreamBasicCollection
    {
        return $this->productStreams;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productStreams->getListingSortings()->count() > 0) {
            $events[] = new ListingSortingBasicLoadedEvent($this->productStreams->getListingSortings(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
