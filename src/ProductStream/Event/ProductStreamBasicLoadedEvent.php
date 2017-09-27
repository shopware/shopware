<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ListingSorting\Event\ListingSortingBasicLoadedEvent;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;

class ProductStreamBasicLoadedEvent extends NestedEvent
{
    const NAME = 'productStream.basic.loaded';

    /**
     * @var ProductStreamBasicCollection
     */
    protected $productStreams;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductStreamBasicCollection $productStreams, TranslationContext $context)
    {
        $this->productStreams = $productStreams;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductStreams(): ProductStreamBasicCollection
    {
        return $this->productStreams;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productStreams->getSortings()->count() > 0) {
            $events[] = new ListingSortingBasicLoadedEvent($this->productStreams->getSortings(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
