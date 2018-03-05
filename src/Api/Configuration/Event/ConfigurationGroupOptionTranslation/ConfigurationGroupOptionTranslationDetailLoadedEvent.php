<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationDetailCollection;
use Shopware\Api\Configuration\Event\ConfigurationGroupOption\ConfigurationGroupOptionBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;

class ConfigurationGroupOptionTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionTranslationDetailCollection
     */
    protected $configurationGroupOptionTranslations;

    public function __construct(ConfigurationGroupOptionTranslationDetailCollection $configurationGroupOptionTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->configurationGroupOptionTranslations = $configurationGroupOptionTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
            $events[] = new ShopBasicLoadedEvent($this->configurationGroupOptionTranslations->getLanguages(), $this->context);
        }
        return new NestedEventCollection($events);
    }            
            
}