<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductDetailTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'product_detail_translation.written';

    /**
     * @var string[]
     */
    private $productDetailTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productDetailTranslationUuids, array $errors = [])
    {
        $this->productDetailTranslationUuids = $productDetailTranslationUuids;
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
    public function getProductDetailTranslationUuids(): array
    {
        return $this->productDetailTranslationUuids;
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
