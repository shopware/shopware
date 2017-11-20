<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormTranslation;

use Shopware\Config\Collection\ConfigFormTranslationDetailCollection;
use Shopware\Config\Event\ConfigForm\ConfigFormBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Event\Locale\LocaleBasicLoadedEvent;

class ConfigFormTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'config_form_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormTranslationDetailCollection
     */
    protected $configFormTranslations;

    public function __construct(ConfigFormTranslationDetailCollection $configFormTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->configFormTranslations = $configFormTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getConfigFormTranslations(): ConfigFormTranslationDetailCollection
    {
        return $this->configFormTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configFormTranslations->getConfigForms()->count() > 0) {
            $events[] = new ConfigFormBasicLoadedEvent($this->configFormTranslations->getConfigForms(), $this->context);
        }
        if ($this->configFormTranslations->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->configFormTranslations->getLocales(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
