<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductStream;

use Shopware\Api\Listing\Event\ListingSorting\ListingSortingBasicLoadedEvent;
use Shopware\Api\Product\Collection\ProductStreamBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductStreamBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product_stream.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductStreamBasicCollection
     */
    protected $productStreams;

    public function __construct(ProductStreamBasicCollection $productStreams, TranslationContext $context)
    {
        $this->context = $context;
        $this->productStreams = $productStreams;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
