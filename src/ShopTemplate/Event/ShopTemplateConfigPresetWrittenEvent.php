<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigPresetWrittenEvent extends NestedEvent
{
    const NAME = 'shop_template_config_preset.written';

    /**
     * @var string[]
     */
    private $shopTemplateConfigPresetUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shopTemplateConfigPresetUuids, array $errors = [])
    {
        $this->shopTemplateConfigPresetUuids = $shopTemplateConfigPresetUuids;
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
    public function getShopTemplateConfigPresetUuids(): array
    {
        return $this->shopTemplateConfigPresetUuids;
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
