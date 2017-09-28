<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigFormFieldValueWrittenEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field_value.written';

    /**
     * @var string[]
     */
    protected $shopTemplateConfigFormFieldValueUuids;

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(array $shopTemplateConfigFormFieldValueUuids, TranslationContext $context, array $errors = [])
    {
        $this->shopTemplateConfigFormFieldValueUuids = $shopTemplateConfigFormFieldValueUuids;
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
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

    public function addEvent(?NestedEvent $event): void
    {
        if ($event === null) {
            return;
        }
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
