<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroup;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupDetailCollection;
use Shopware\Api\Configuration\Event\ConfigurationGroupOption\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationBasicLoadedEvent;

class ConfigurationGroupDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupDetailCollection
     */
    protected $configurationGroups;

    public function __construct(ConfigurationGroupDetailCollection $configurationGroups, ShopContext $context)
    {
        $this->context = $context;
        $this->configurationGroups = $configurationGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getConfigurationGroups(): ConfigurationGroupDetailCollection
    {
        return $this->configurationGroups;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configurationGroups->getOptions()->count() > 0) {
            $events[] = new ConfigurationGroupOptionBasicLoadedEvent($this->configurationGroups->getOptions(), $this->context);
        }
        if ($this->configurationGroups->getTranslations()->count() > 0) {
            $events[] = new ConfigurationGroupTranslationBasicLoadedEvent($this->configurationGroups->getTranslations(), $this->context);
        }
        return new NestedEventCollection($events);
    }            
            
}