<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection
     */
    protected $configurationGroupOptionTranslations;

    public function __construct(ConfigurationGroupOptionTranslationBasicCollection $configurationGroupOptionTranslations, Context $context)
    {
        $this->context = $context;
        $this->configurationGroupOptionTranslations = $configurationGroupOptionTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigurationGroupOptionTranslations(): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->configurationGroupOptionTranslations;
    }
}
