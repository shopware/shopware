<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Event;

use Shopware\Core\Content\Configuration\Collection\ConfigurationGroupBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ConfigurationGroupBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ConfigurationGroupBasicCollection
     */
    protected $configurationGroups;

    public function __construct(ConfigurationGroupBasicCollection $configurationGroups, Context $context)
    {
        $this->context = $context;
        $this->configurationGroups = $configurationGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigurationGroups(): ConfigurationGroupBasicCollection
    {
        return $this->configurationGroups;
    }
}
