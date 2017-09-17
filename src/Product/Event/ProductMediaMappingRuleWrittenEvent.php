<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductMediaMappingRuleWrittenEvent extends NestedEvent
{
    const NAME = 'product_media_mapping_rule.written';

    /**
     * @var string[]
     */
    private $productMediaMappingRuleUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productMediaMappingRuleUuids, array $errors = [])
    {
        $this->productMediaMappingRuleUuids = $productMediaMappingRuleUuids;
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
    public function getProductMediaMappingRuleUuids(): array
    {
        return $this->productMediaMappingRuleUuids;
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
