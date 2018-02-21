<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductMedia;

use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductMediaBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_media.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ProductMediaBasicCollection
     */
    protected $productMedia;

    public function __construct(ProductMediaBasicCollection $productMedia, ShopContext $context)
    {
        $this->context = $context;
        $this->productMedia = $productMedia;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
