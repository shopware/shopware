<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Event;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Collection\ConfigurationGroupOptionDetailCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event\ConfigurationGroupOptionTranslationBasicLoadedEvent;
use Shopware\Core\Content\Configuration\Event\ConfigurationGroupBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ConfigurationGroupOptionDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ConfigurationGroupOptionDetailCollection
     */
    protected $configurationGroupOptions;

    public function __construct(ConfigurationGroupOptionDetailCollection $configurationGroupOptions, Context $context)
    {
        $this->context = $context;
        $this->configurationGroupOptions = $configurationGroupOptions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
