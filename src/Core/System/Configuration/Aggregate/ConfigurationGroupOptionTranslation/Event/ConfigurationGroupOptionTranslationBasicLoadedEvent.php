<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection
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
