<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroup;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupBasicCollection;


class ConfigurationGroupBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupBasicCollection
     */
    protected $configurationGroups;

    public function __construct(ConfigurationGroupBasicCollection $configurationGroups, ShopContext $context)
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

    public function getConfigurationGroups(): ConfigurationGroupBasicCollection
    {
        return $this->configurationGroups;
    }

}