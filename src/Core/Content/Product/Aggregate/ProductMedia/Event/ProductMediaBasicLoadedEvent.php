<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductMedia\Event;

use Shopware\Framework\Context;
use Shopware\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Content\Product\Aggregate\ProductMedia\Collection\ProductMediaBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductMediaBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_media.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductMedia\Collection\ProductMediaBasicCollection
     */
    protected $productMedia;

    public function __construct(ProductMediaBasicCollection $productMedia, Context $context)
    {
        $this->context = $context;
        $this->productMedia = $productMedia;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductMedia(): ProductMediaBasicCollection
    {
        return $this->productMedia;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productMedia->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->productMedia->getMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
