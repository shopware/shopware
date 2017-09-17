<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductPriceWrittenEvent extends NestedEvent
{
    const NAME = 'product_price.written';

    /**
     * @var string[]
     */
    private $productPriceUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productPriceUuids, array $errors = [])
    {
        $this->productPriceUuids = $productPriceUuids;
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
    public function getProductPriceUuids(): array
    {
        return $this->productPriceUuids;
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
