<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductConfiguratorGroupTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'product_configurator_group_translation.written';

    /**
     * @var string[]
     */
    private $productConfiguratorGroupTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productConfiguratorGroupTranslationUuids, array $errors = [])
    {
        $this->productConfiguratorGroupTranslationUuids = $productConfiguratorGroupTranslationUuids;
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
    public function getProductConfiguratorGroupTranslationUuids(): array
    {
        return $this->productConfiguratorGroupTranslationUuids;
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
