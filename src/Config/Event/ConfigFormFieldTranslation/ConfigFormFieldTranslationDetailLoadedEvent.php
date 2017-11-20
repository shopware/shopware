<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormFieldTranslation;

use Shopware\Config\Collection\ConfigFormFieldTranslationDetailCollection;
use Shopware\Config\Event\ConfigFormField\ConfigFormFieldBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Event\Locale\LocaleBasicLoadedEvent;

class ConfigFormFieldTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'config_form_field_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldTranslationDetailCollection
     */
    protected $configFormFieldTranslations;

    public function __construct(ConfigFormFieldTranslationDetailCollection $configFormFieldTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->configFormFieldTranslations = $configFormFieldTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
