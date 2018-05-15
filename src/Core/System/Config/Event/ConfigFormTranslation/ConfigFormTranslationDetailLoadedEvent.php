<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormTranslation;

use Shopware\System\Config\Collection\ConfigFormTranslationDetailCollection;
use Shopware\System\Config\Event\ConfigForm\ConfigFormBasicLoadedEvent;
use Shopware\System\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ConfigFormTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigFormTranslationDetailCollection
     */
    protected $configFormTranslations;

    public function __construct(ConfigFormTranslationDetailCollection $configFormTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configFormTranslations = $configFormTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
