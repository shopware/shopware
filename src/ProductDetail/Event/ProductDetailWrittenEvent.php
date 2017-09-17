<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductDetailWrittenEvent extends NestedEvent
{
    const NAME = 'product_detail.written';

    /**
     * @var string[]
     */
    private $productDetailUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productDetailUuids, array $errors = [])
    {
        $this->productDetailUuids = $productDetailUuids;
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
    public function getProductDetailUuids(): array
    {
        return $this->productDetailUuids;
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
