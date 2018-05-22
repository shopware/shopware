<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldValue\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Config\Aggregate\ConfigFormField\Event\ConfigFormFieldBasicLoadedEvent;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\Collection\ConfigFormFieldValueDetailCollection;

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

        return new NestedEventCollection($events);
    }
}
