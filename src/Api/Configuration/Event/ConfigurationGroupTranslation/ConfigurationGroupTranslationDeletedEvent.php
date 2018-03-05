<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupTranslationDefinition;

class ConfigurationGroupTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'configuration_group_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupTranslationDefinition::class;
    }
}
