<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionDetailCollection;
use Shopware\Api\Configuration\Event\ConfigurationGroup\ConfigurationGroupBasicLoadedEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationBasicLoadedEvent;

class ConfigurationGroupOptionDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionDetailCollection
     */
    protected $configurationGroupOptions;

    public function __construct(ConfigurationGroupOptionDetailCollection $configurationGroupOptions, ShopContext $context)
    {
        $this->context = $context;
        $this->configurationGroupOptions = $configurationGroupOptions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
        if ($this->configurationGroupOptions->getConfigurationGroups()->count() > 0) {
            $events[] = new ConfigurationGroupBasicLoadedEvent($this->configurationGroupOptions->getConfigurationGroups(), $this->context);
        }
        if ($this->configurationGroupOptions->getTranslations()->count() > 0) {
            $events[] = new ConfigurationGroupOptionTranslationBasicLoadedEvent($this->configurationGroupOptions->getTranslations(), $this->context);
        }
        return new NestedEventCollection($events);
    }            
            
}