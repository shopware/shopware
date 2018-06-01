<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationDetailCollection;
use Shopware\System\Configuration\Event\ConfigurationGroupBasicLoadedEvent;

class ConfigurationGroupTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationDetailCollection
     */
    protected $configurationGroupTranslations;

    public function __construct(ConfigurationGroupTranslationDetailCollection $configurationGroupTranslations, Context $context)
    {
        $this->context = $context;
        $this->configurationGroupTranslations = $configurationGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigurationGroupTranslations(): ConfigurationGroupTranslationDetailCollection
    {
        return $this->configurationGroupTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configurationGroupTranslations->getConfigurationGroups()->count() > 0) {
            $events[] = new ConfigurationGroupBasicLoadedEvent($this->configurationGroupTranslations->getConfigurationGroups(), $this->context);
        }
        if ($this->configurationGroupTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->configurationGroupTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
