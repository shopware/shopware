<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\Collection\ProductMediaDetailCollection;
use Shopware\Core\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ProductMediaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_media.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ProductMediaDetailCollection
     */
    protected $productMedia;

    public function __construct(ProductMediaDetailCollection $productMedia, Context $context)
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

    public function getProductMedia(): ProductMediaDetailCollection
    {
        return $this->productMedia;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productMedia->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productMedia->getProducts(), $this->context);
        }
        if ($this->productMedia->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->productMedia->getMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
