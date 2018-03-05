<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupTranslationDefinition;

class ConfigurationGroupTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'configuration_group_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupTranslationDefinition::class;
    }
}