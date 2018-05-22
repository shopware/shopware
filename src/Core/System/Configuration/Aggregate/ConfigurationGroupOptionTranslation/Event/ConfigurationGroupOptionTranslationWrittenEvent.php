<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDefinition;

class ConfigurationGroupOptionTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'configuration_group_option_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupOptionTranslationDefinition::class;
    }
}
