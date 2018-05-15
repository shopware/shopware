<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\System\Configuration\Collection\ConfigurationGroupTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigurationGroupTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigurationGroupTranslationBasicCollection
     */
    protected $configurationGroupTranslations;

    public function __construct(ConfigurationGroupTranslationBasicCollection $configurationGroupTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configurationGroupTranslations = $configurationGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getConfigurationGroupTranslations(): ConfigurationGroupTranslationBasicCollection
    {
        return $this->configurationGroupTranslations;
    }
}
