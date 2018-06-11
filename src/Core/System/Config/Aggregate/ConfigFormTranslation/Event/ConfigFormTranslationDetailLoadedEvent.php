<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationDetailCollection;
use Shopware\Core\System\Config\Event\ConfigFormBasicLoadedEvent;
use Shopware\Core\System\Locale\Event\LocaleBasicLoadedEvent;

class ConfigFormTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ConfigFormTranslationDetailCollection
     */
    protected $configFormTranslations;

    public function __construct(ConfigFormTranslationDetailCollection $configFormTranslations, Context $context)
    {
        $this->context = $context;
        $this->configFormTranslations = $configFormTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
