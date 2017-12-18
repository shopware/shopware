<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigFormFieldValue;

use Shopware\Api\Config\Collection\ConfigFormFieldValueDetailCollection;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ConfigFormFieldValueDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_value.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldValueDetailCollection
     */
    protected $configFormFieldValues;

    public function __construct(ConfigFormFieldValueDetailCollection $configFormFieldValues, TranslationContext $context)
    {
        $this->context = $context;
        $this->configFormFieldValues = $configFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getConfigFormFieldValues(): ConfigFormFieldValueDetailCollection
    {
        return $this->configFormFieldValues;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configFormFieldValues->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->configFormFieldValues->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
