<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;


class ConfigurationGroupOptionTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionTranslationBasicCollection
     */
    protected $configurationGroupOptionTranslations;

    public function __construct(ConfigurationGroupOptionTranslationBasicCollection $configurationGroupOptionTranslations, ShopContext $context)
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

    public function getConfigurationGroupOptionTranslations(): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->configurationGroupOptionTranslations;
    }

}