<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationBasicCollection;


class ConfigurationGroupTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupTranslationBasicCollection
     */
    protected $configurationGroupTranslations;

    public function __construct(ConfigurationGroupTranslationBasicCollection $configurationGroupTranslations, ShopContext $context)
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

    public function getConfigurationGroupTranslations(): ConfigurationGroupTranslationBasicCollection
    {
        return $this->configurationGroupTranslations;
    }

}