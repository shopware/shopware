<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;


class ConfigurationGroupOptionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionBasicCollection
     */
    protected $configurationGroupOptions;

    public function __construct(ConfigurationGroupOptionBasicCollection $configurationGroupOptions, ShopContext $context)
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

    public function getConfigurationGroupOptions(): ConfigurationGroupOptionBasicCollection
    {
        return $this->configurationGroupOptions;
    }

}