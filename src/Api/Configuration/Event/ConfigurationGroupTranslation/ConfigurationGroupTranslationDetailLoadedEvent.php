<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationDetailCollection;
use Shopware\Api\Configuration\Event\ConfigurationGroup\ConfigurationGroupBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;

class ConfigurationGroupTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupTranslationDetailCollection
     */
    protected $configurationGroupTranslations;

    public function __construct(ConfigurationGroupTranslationDetailCollection $configurationGroupTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->configurationGroupTranslations = $configurationGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
            $events[] = new ShopBasicLoadedEvent($this->configurationGroupTranslations->getLanguages(), $this->context);
        }
        return new NestedEventCollection($events);
    }            
            
}