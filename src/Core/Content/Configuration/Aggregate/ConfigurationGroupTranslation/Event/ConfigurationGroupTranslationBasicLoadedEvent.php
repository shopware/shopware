<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationBasicCollection;

class ConfigurationGroupTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationBasicCollection
     */
    protected $configurationGroupTranslations;

    public function __construct(ConfigurationGroupTranslationBasicCollection $configurationGroupTranslations, Context $context)
    {
        $this->context = $context;
        $this->configurationGroupTranslations = $configurationGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigurationGroupTranslations(): ConfigurationGroupTranslationBasicCollection
    {
        return $this->configurationGroupTranslations;
    }
}
