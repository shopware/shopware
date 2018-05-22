<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Event\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationDetailCollection;

class ConfigurationGroupOptionTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionTranslationDetailCollection
     */
    protected $configurationGroupOptionTranslations;

    public function __construct(ConfigurationGroupOptionTranslationDetailCollection $configurationGroupOptionTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configurationGroupOptionTranslations = $configurationGroupOptionTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
