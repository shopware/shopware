<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigFormFieldValueWrittenEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field_value.written';

    /**
     * @var string[]
     */
    private $shopTemplateConfigFormFieldValueUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shopTemplateConfigFormFieldValueUuids, array $errors = [])
    {
        $this->shopTemplateConfigFormFieldValueUuids = $shopTemplateConfigFormFieldValueUuids;
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
    public function getShopTemplateConfigFormFieldValueUuids(): array
    {
        return $this->shopTemplateConfigFormFieldValueUuids;
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
