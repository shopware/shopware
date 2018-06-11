<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\Event\ConfigFormFieldBasicLoadedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationDetailCollection;
use Shopware\Core\System\Locale\Event\LocaleBasicLoadedEvent;

class ConfigFormFieldTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ConfigFormFieldTranslationDetailCollection
     */
    protected $configFormFieldTranslations;

    public function __construct(ConfigFormFieldTranslationDetailCollection $configFormFieldTranslations, Context $context)
    {
        $this->context = $context;
        $this->configFormFieldTranslations = $configFormFieldTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigFormFieldTranslations(): ConfigFormFieldTranslationDetailCollection
    {
        return $this->configFormFieldTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configFormFieldTranslations->getConfigFormFields()->count() > 0) {
            $events[] = new ConfigFormFieldBasicLoadedEvent($this->configFormFieldTranslations->getConfigFormFields(), $this->context);
        }
        if ($this->configFormFieldTranslations->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->configFormFieldTranslations->getLocales(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
