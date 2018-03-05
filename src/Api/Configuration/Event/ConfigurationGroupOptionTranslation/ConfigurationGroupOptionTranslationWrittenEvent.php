<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupOptionTranslationDefinition;

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