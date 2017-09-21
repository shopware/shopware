<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductDetailPriceWrittenEvent extends NestedEvent
{
    const NAME = 'product_detail_price.written';

    /**
     * @var string[]
     */
    private $productDetailPriceUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productDetailPriceUuids, array $errors = [])
    {
        $this->productDetailPriceUuids = $productDetailPriceUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getProductDetailPriceUuids(): array
    {
        return $this->productDetailPriceUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
