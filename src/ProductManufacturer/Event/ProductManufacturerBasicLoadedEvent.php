<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;

class ProductManufacturerBasicLoadedEvent extends NestedEvent
{
    const NAME = 'productManufacturer.basic.loaded';

    /**
     * @var ProductManufacturerBasicCollection
     */
    protected $productManufacturers;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductManufacturerBasicCollection $productManufacturers, TranslationContext $context)
    {
        $this->productManufacturers = $productManufacturers;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductManufacturers(): ProductManufacturerBasicCollection
    {
        return $this->productManufacturers;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
