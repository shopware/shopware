<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigPresetWrittenEvent extends NestedEvent
{
    const NAME = 'shop_template_config_preset.written';

    /**
     * @var string[]
     */
    protected $shopTemplateConfigPresetUuids;

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

    public function __construct(array $shopTemplateConfigPresetUuids, TranslationContext $context, array $errors = [])
    {
        $this->shopTemplateConfigPresetUuids = $shopTemplateConfigPresetUuids;
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
