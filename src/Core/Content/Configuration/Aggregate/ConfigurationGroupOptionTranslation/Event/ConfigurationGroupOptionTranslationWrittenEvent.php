<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
