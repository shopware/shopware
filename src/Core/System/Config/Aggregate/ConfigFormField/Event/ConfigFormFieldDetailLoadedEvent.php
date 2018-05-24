<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldDetailCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Event\ConfigFormFieldTranslationBasicLoadedEvent;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\Event\ConfigFormFieldValueBasicLoadedEvent;
use Shopware\System\Config\Event\ConfigFormBasicLoadedEvent;

class ConfigFormFieldDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldDetailCollection
     */
    protected $configFormFields;

    public function __construct(ConfigFormFieldDetailCollection $configFormFields, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configFormFields = $configFormFields;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getConfigFormFields(): ConfigFormFieldDetailCollection
    {
        return $this->configFormFields;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configFormFields->getConfigForms()->count() > 0) {
            $events[] = new ConfigFormBasicLoadedEvent($this->configFormFields->getConfigForms(), $this->context);
        }
        if ($this->configFormFields->getTranslations()->count() > 0) {
            $events[] = new ConfigFormFieldTranslationBasicLoadedEvent($this->configFormFields->getTranslations(), $this->context);
        }
        if ($this->configFormFields->getValues()->count() > 0) {
            $events[] = new ConfigFormFieldValueBasicLoadedEvent($this->configFormFields->getValues(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
