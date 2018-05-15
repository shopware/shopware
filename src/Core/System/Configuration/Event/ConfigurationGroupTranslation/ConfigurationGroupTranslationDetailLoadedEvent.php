<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\System\Configuration\Collection\ConfigurationGroupTranslationDetailCollection;
use Shopware\System\Configuration\Event\ConfigurationGroup\ConfigurationGroupBasicLoadedEvent;
use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ConfigurationGroupTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupTranslationDetailCollection
     */
    protected $configurationGroupTranslations;

    public function __construct(ConfigurationGroupTranslationDetailCollection $configurationGroupTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configurationGroupTranslations = $configurationGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
