<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Event\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationDetailCollection;

class ConfigurationGroupOptionTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionTranslationDetailCollection
     */
    protected $configurationGroupOptionTranslations;

    public function __construct(ConfigurationGroupOptionTranslationDetailCollection $configurationGroupOptionTranslations, Context $context)
    {
        $this->context = $context;
        $this->configurationGroupOptionTranslations = $configurationGroupOptionTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigurationGroupOptionTranslations(): ConfigurationGroupOptionTranslationDetailCollection
    {
        return $this->configurationGroupOptionTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configurationGroupOptionTranslations->getConfigurationGroupOptions()->count() > 0) {
            $events[] = new ConfigurationGroupOptionBasicLoadedEvent($this->configurationGroupOptionTranslations->getConfigurationGroupOptions(), $this->context);
        }
        if ($this->configurationGroupOptionTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->configurationGroupOptionTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
