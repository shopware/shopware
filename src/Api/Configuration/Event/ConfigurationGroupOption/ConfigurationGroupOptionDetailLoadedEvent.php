<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionDetailCollection;
use Shopware\Api\Configuration\Event\ConfigurationGroup\ConfigurationGroupBasicLoadedEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ConfigurationGroupOptionDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionDetailCollection
     */
    protected $configurationGroupOptions;

    public function __construct(ConfigurationGroupOptionDetailCollection $configurationGroupOptions, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configurationGroupOptions = $configurationGroupOptions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getConfigurationGroupOptions(): ConfigurationGroupOptionDetailCollection
    {
        return $this->configurationGroupOptions;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configurationGroupOptions->getGroups()->count() > 0) {
            $events[] = new ConfigurationGroupBasicLoadedEvent($this->configurationGroupOptions->getGroups(), $this->context);
        }
        if ($this->configurationGroupOptions->getTranslations()->count() > 0) {
            $events[] = new ConfigurationGroupOptionTranslationBasicLoadedEvent($this->configurationGroupOptions->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
