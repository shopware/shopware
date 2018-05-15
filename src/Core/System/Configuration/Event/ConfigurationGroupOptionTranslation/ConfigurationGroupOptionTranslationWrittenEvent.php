<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroupOptionTranslation;

use Shopware\System\Configuration\Definition\ConfigurationGroupOptionTranslationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
