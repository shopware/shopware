<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Event;

use Shopware\Core\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ProductManufacturerDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ProductManufacturerDetailCollection
     */
    protected $productManufacturers;

    public function __construct(ProductManufacturerDetailCollection $productManufacturers, Context $context)
    {
        $this->context = $context;
        $this->productManufacturers = $productManufacturers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductManufacturers(): ProductManufacturerDetailCollection
    {
        return $this->productManufacturers;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productManufacturers->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->productManufacturers->getMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
