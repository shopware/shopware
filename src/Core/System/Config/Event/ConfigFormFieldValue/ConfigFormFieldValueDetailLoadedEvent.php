<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormFieldValue;

use Shopware\System\Config\Collection\ConfigFormFieldValueDetailCollection;
use Shopware\System\Config\Event\ConfigFormField\ConfigFormFieldBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ConfigFormFieldValueDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_value.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldValueDetailCollection
     */
    protected $configFormFieldValues;

    public function __construct(ConfigFormFieldValueDetailCollection $configFormFieldValues, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configFormFieldValues = $configFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
        if ($this->configFormFieldValues->getConfigFormFields()->count() > 0) {
            $events[] = new ConfigFormFieldBasicLoadedEvent($this->configFormFieldValues->getConfigFormFields(), $this->context);
        }
        if ($this->configFormFieldValues->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->configFormFieldValues->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
