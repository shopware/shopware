<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductConfiguratorSetGroupRelationWrittenEvent extends NestedEvent
{
    const NAME = 'product_configurator_set_group_relation.written';

    /**
     * @var string[]
     */
    private $productConfiguratorSetGroupRelationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productConfiguratorSetGroupRelationUuids, array $errors = [])
    {
        $this->productConfiguratorSetGroupRelationUuids = $productConfiguratorSetGroupRelationUuids;
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
    public function getProductConfiguratorSetGroupRelationUuids(): array
    {
        return $this->productConfiguratorSetGroupRelationUuids;
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
