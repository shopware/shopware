<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductManufacturer;

use Shopware\Content\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Content\Product\Collection\ProductManufacturerDetailCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductManufacturerDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerDetailCollection
     */
    protected $productManufacturers;

    public function __construct(ProductManufacturerDetailCollection $productManufacturers, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productManufacturers = $productManufacturers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
